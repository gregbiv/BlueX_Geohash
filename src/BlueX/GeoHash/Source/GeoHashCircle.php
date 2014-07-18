<?php
namespace BlueX\GeoHash\Source;

/**
 * Computes geohash sets in widening circles around a center point.
 *
 * NOTE: This does not work very well near the poles, and this library should not be relied on for those edge cases.
 *       Some serious dancing around the math has to be done for us to effectively use geohashes near the poles.
 *       Perhaps another location paradigm should be used?  I.e. It may be better to just use a latitude, longitude sort.
 */
class GeoHashCircle
{
    /**
     * @var string
     */
    private $center_geohash;

    /**
     * @var integer
     */
    private $precision;

    /**
     * @var \BlueX\GeoHash\Source\GeoBox
     */
    private $geobox;

    /**
     * @var \BlueX\GeoHash\Source\GeoPoint
     */
    private $center;

    /**
     * @var \BlueX\GeoHash\Source\GeoHashSet
     */
    private $geohash_set;

    /**
     * @var integer
     */
    private $max_radius;

    /**
     * Creates a new geohash circle, centered around a geohash center.
     *
     * @param string $center_geohash
     */
    public function __construct($center_geohash)
    {
        $this->center_geohash = $center_geohash;
        $this->precision      = strlen($center_geohash);

        $this->geobox = GeoHash::decodeBox($center_geohash);
        $this->center = $this->geobox->center();

        // Begin with a set including only the box itself
        $this->geohash_set = new GeoHashSet();
        $this->geohash_set->add($center_geohash);

        // The distance to the longitude will always be less than the distance to the latitude (I think)
        $this->max_radius = $this->center->distanceToLongitude($this->geobox->east);
    }

    /**
     * Returns the distance from the center point of this circle to a geopoint.
     *
     * @param \BlueX\GeoHash\Source\GeoPoint $point
     *
     * @return float distance in kilometers
     */
    public function distanceToPoint(GeoPoint $point)
    {
        return $this->center->distanceToPoint($point);
    }

    /**
     * Returns the maximum reliable radius for a geohash query generated from this circle.
     * Some points may be in the queried set, but outside the circle (i.e. the corner points).
     * @return float distance in kilometers
     */
    public function max_radius()
    {
        return $this->max_radius;
    }

    /**
     * TODO: Should this return true if the point is inside the geohash set or the circle?
     *
     * @param \BlueX\GeoHash\Source\GeoPoint $point
     *
     * @return boolean
     */
    public function contains($point)
    {
        return $this->geohash_set->contains($point);
    }

    /**
     * Increase the size the of the circle.
     *
     * @param integer $amount
     *
     * @return boolean success|failure
     */
    public function expand($amount = 1)
    {
        if ($this->precision == 1) {
            // currently unwilling to expand to cover the entire globe
            return false;
        }
        $this->precision = max($this->precision - $amount, 1);

        // shrink the geohash, grow the box
        $center = substr($this->center_geohash, 0, $this->precision);
        $box    = GeoHash::decodeBox($center);
        $dlat   = $box->north - $box->south;
        $dlon   = $box->east - $box->west;

        $set = new GeoHashSet();
        $set->add($center);

        $quadrant = GeoHash::quadrant($this->center_geohash, $this->precision);
        if ($quadrant == GeoHash::NORTHEAST || $quadrant == GeoHash::NORTHWEST) {
            // north side
            $NORTH = GeoHash::neighbor($center, GeoHash::NORTH);
            if ($NORTH) {
                $set->addSet(GeoHash::halve($NORTH, GeoHash::SOUTH));
                if ($quadrant == GeoHash::NORTHEAST) {
                    $set->addSet(GeoHash::quarter(GeoHash::neighbor($NORTH, GeoHash::EAST), GeoHash::SOUTHWEST));
                } else { // northwest
                    $set->addSet(GeoHash::quarter(GeoHash::neighbor($NORTH, GeoHash::WEST), GeoHash::SOUTHEAST));
                }
                $box->north += $dlat / 2;
            }
        } else {
            // south side
            $south = GeoHash::neighbor($center, GeoHash::SOUTH);
            if ($south) {
                $set->addSet(GeoHash::halve($south, GeoHash::NORTH));
                if ($quadrant == GeoHash::SOUTHEAST) {
                    $set->addSet(GeoHash::quarter(GeoHash::neighbor($south, GeoHash::EAST), GeoHash::NORTHWEST));
                } else { // southwest
                    $set->addSet(GeoHash::quarter(GeoHash::neighbor($south, GeoHash::WEST), GeoHash::NORTHEAST));
                }
                $box->south -= $dlat / 2;
            }
        }

        if ($quadrant == GeoHash::NORTHEAST || $quadrant == GeoHash::SOUTHEAST) {
            // east side
            $set->addSet(GeoHash::halve(GeoHash::neighbor($center, GeoHash::EAST), GeoHash::WEST));
            $box->east += $dlon / 2;
        } else {
            // west side
            $set->addSet(GeoHash::halve(GeoHash::neighbor($center, GeoHash::WEST), GeoHash::EAST));
            $box->west -= $dlon / 2;
        }

        $this->geohash_set = $set;

        $this->max_radius = min($this->center->distanceToLongitude($box->east),
            $this->center->distanceToLongitude($box->west));
        if ($box->north < 90) {
            $this->max_radius = min($this->center->distanceToLatitude(min($box->north, 90)), $this->max_radius);
        }
        if ($box->south > -90) {
            $this->max_radius = min($this->center->distanceToLatitude(max($box->south, -90)), $this->max_radius);
        }

        return true;
    }
}