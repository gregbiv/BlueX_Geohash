<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Chris Johnson
 * @author  Gregory Kornienko <gregbiv@gmail.com>
 * @license MIT
 */
namespace BlueX\GeoHash\Source;

/**
 * A location on the earth denoted by latitude and longitude.
 * The latitude indicates the distance north or south of the equator,
 * -90 being the south pole, 0 being the equator and +90 being the north pole.
 * The longitude indicates an east west distance from the prime meridian,
 * the Grenich Meridian pasing through the Royal Observatory in Greenwich
 * or for our purposes 0.   The longitudes proceed west meeting the international
 * date line at -180, and east meeting the same from the other direction at +180.
 */
class GeoPoint
{
    const EARTH_RADIUS = 6371; // mean radius in km

    /**
     * @var float
     */
    public $latitude;

    /**
     * @var float
     */
    public $longitude;

    /**
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct($latitude, $longitude)
    {
        $this->latitude  = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Convenience method to convert this GeoPoint to a GeoPoint.
     * @see geohash::encode
     *
     * @param int $precision
     *
     * @return \BlueX\GeoHash\Source\GeoHash
     */
    public function geohash($precision = 8)
    {
        return GeoHash::encode($this, $precision);
    }

    /**
     * Returns the distance between two geopoints in kilometers.
     *
     * @param \BlueX\GeoHash\Source\GeoPoint $point
     *
     * @return float distance in kilometers
     */
    public function distanceToPoint($point)
    {
        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);

        $lat2 = deg2rad($point->latitude);
        $lon2 = deg2rad($point->longitude);

        // spherical law of cosines (accurate to ~1m w/64 bit floats)
        return acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lon1 - $lon2)) * self::EARTH_RADIUS;
    }

    /**
     * Returns the distance from this point to a latitudinal line.
     *
     * @param float $latitude -90 to 90
     *
     * @return float distance in kilometers
     */
    public function distanceToLatitude($latitude)
    {
        return $this->distanceToPoint(new GeoPoint($latitude, $this->longitude));
    }

    /**
     * Returns the distance from this point to a longitudinal line.
     * http://williams.best.vwh.net/avform.htm#Int
     *
     * @param float $longitude -180 and 180
     * @param       float      distance in kilometers
     *
     * @return float
     */
    public function distanceToLongitude($longitude)
    {
        $lon3 = deg2rad($longitude);

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);

        // equatorial point perpendicular to the longitudal great circle
        $lat2 = 0;
        $lon2 = $lon3 + M_PI / 2;

        if (abs(sin($lon1 - $lon2)) < 1E-12) {
            // the meridian case: sin($lon1 - $lon2) = 0, produces divide by zero error
            $lat3 = ($lat1 >= 0 ? 1 : -1) * self::earth_radius * M_PI / 2;
        } elseif (M_PI / 2 - abs($lat1) < 1E-12) {
            // the distance from a pole to any meridian will be 0
            return 0;
        } else {
            $lat3 = atan(
                (sin($lat1) * cos($lat2) * sin($lon3 - $lon2) - sin($lat2) * cos($lat1) * sin($lon3 - $lon1)) /
                (cos($lat1) * cos($lat2) * sin($lon1 - $lon2))
            );
        }

        $point3 = new GeoPoint(rad2deg($lat3), rad2deg($lon3));

        return $this->distanceToPoint($point3);
    }
}
