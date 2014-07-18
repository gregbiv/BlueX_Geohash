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
 * A collection of geohashes or geohash ranges.
 */
class GeoHashSet
{
    private $geohashes = array();

    /**
     * @param string $geohash
     */
    public function add($geohash)
    {
        $this->geohashes[] = $geohash;
    }

    /**
     * @param string $first
     * @param string $last
     */
    public function addRange($first, $last)
    {
        $this->geohashes[] = array($first, $last);
    }

    /**
     * @param \BlueX\GeoHash\Source\GeoHashSet $set
     */
    public function addSet($set)
    {
        foreach ($set->geohashes as $geohash) {
            $this->geohashes[] = $geohash;
        }
    }

    /**
     * Returns true if the point falls within one of the hash boxes.
     *
     * @param \BlueX\GeoHash\Source\GeoPoint $point
     *
     * @return boolean
     */
    public function contains(GeoPoint $point)
    {
        foreach ($this->geohashes as $geohash) {
            if (is_array($geohash)) {
                $first = $geohash[0];
                $last  = $geohash[1];
                $test  = $first;

                while ($test <= $last && $test) {
                    if (GeoHash::contains($test, $point)) {
                        return true;
                    }
                    $test = GeoHash::increment($test);
                }
            } else {
                if (GeoHash::contains($geohash, $point)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns an array of geohashes.
     * Some of the array elements may be arrays containing the start and endpoints of a range of geohashes.
     *
     * @return array
     */
    public function export()
    {
        return $this->geohashes;
    }
}
