<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Chris Johnson
 * @author  Gregory Kornienko <gregbiv@gmail.com>
 * @license MIT
 */
namespace BlueX\Geo;

/**
 * A collection of geohashes or geohash ranges.
 */
class HashSet
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
     * @param \BlueX\Geo\HashSet $set
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
     * @param \BlueX\Geo\Point $point
     *
     * @return boolean
     */
    public function contains(Point $point)
    {
        foreach ($this->geohashes as $geohash) {
            if (is_array($geohash)) {
                $first = $geohash[0];
                $last  = $geohash[1];
                $test  = $first;

                while ($test <= $last && $test) {
                    if (Hash::contains($test, $point)) {
                        return true;
                    }
                    $test = Hash::increment($test);
                }
            } else {
                if (Hash::contains($geohash, $point)) {
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
