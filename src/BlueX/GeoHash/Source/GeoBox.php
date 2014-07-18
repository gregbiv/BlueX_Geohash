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
 * Circumscribes an area of the earth.  The edges are marked by a single
 * lat/long pair marking the north, south, east and west boundaries.
 */
class GeoBox
{
    /**
     * @var float
     */
    public $north;

    /**
     * @var float
     */
    public $south;

    /**
     * @var float
     */
    public $east;

    /**
     * @var float
     */
    public $west;

    /**
     * @param \BlueX\GeoHash\Source\GeoPoint $p1
     * @param \BlueX\GeoHash\Source\GeoPoint $p2
     */
    public function __construct($p1, $p2)
    {
        $this->north = max($p1->latitude, $p2->latitude);
        $this->south = min($p1->latitude, $p2->latitude);
        $this->east  = max($p1->longitude, $p2->longitude);
        $this->west  = min($p1->longitude, $p2->longitude);
    }

    /**
     * Returns the center point of this box.
     * @return \BlueX\GeoHash\Source\GeoPoint
     */
    public function center()
    {
        return new GeoPoint(($this->north + $this->south) / 2, ($this->east + $this->west) / 2);
    }

    /**
     * Returns the northeast corner of the box.
     * @return \BlueX\GeoHash\Source\GeoPoint
     */
    public function northeast()
    {
        return new GeoPoint($this->north, $this->east);
    }

    /**
     * Returns the northwest corner of the box.
     * @return \BlueX\GeoHash\Source\GeoPoint
     */
    public function northwest()
    {
        return new GeoPoint($this->north, $this->west);
    }

    /**
     * Returns the southeast corner of the box.
     * @return \BlueX\GeoHash\Source\GeoPoint
     */
    public function southeast()
    {
        return new GeoPoint($this->south, $this->east);
    }

    /**
     * Returns the southwest corner of the box.
     * @return \BlueX\GeoHash\Source\GeoPoint
     */
    public function southwest()
    {
        return new GeoPoint($this->south, $this->west);
    }
}
