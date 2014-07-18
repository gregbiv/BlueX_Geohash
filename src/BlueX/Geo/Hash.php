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
 * A geohash is a Z-order, or Morton curve, filling the earth's longitudes and latitudes
 * starting at -90 -180 to the left then up and finally ending up at 90 180.  It uses a
 * base-32 character encoding, and has the characteristic of getting less precise as you
 * remove characters from the end of the hash.
 *
 * http://en.wikipedia.org/wiki/Geohash
 * http://en.wikipedia.org/wiki/Z-order_(curve)
 */
class Hash
{
    const ENCODING = '0123456789bcdefghjkmnpqrstuvwxyz';

    // each index maps to the neighboring encoded digit, border digits wrap
    const ODD_NORTH_NEIGHBOR = '238967debc01fg45kmstqrwxuvhjyznp';
    const ODD_SOUTH_NEIGHBOR = 'bc01fg45238967deuvhjyznpkmstqrwx';
    const ODD_EAST_NEIGHBOR  = '14365h7k9dcfesgujnmqp0r2twvyx8zb';
    const ODD_WEST_NEIGHBOR  = 'p0r21436x8zb9dcf5h7kjnmqesgutwvy';

    const MAX_PREFIX_LENGTH = 60;

    const ODD_NORTH_BORDER = 'bcfguvyz';
    const ODD_SOUTH_BORDER = '0145hjnp';
    const ODD_EAST_BORDER  = 'prxz';
    const ODD_WEST_BORDER  = '028b';

    const NORTH     = 'n';
    const SOUTH     = 's';
    const EAST      = 'e';
    const WEST      = 'w';
    const NORTHEAST = 'ne';
    const NORTHWEST = 'nw';
    const SOUTHEAST = 'se';
    const SOUTHWEST = 'sw';

    /**
     * Returns the geohash encoded 'digit' of a number between 0 and 31.
     *
     * @param integer $number
     *
     * @return string
     */
    public static function encodeDigit($number)
    {
        $encoding = self::ENCODING;

        return $encoding[$number];
    }

    /**
     * Returns the int between 0 and 31 corresponding to a geohash digit.
     *
     * @param string $digit
     *
     * @return int
     */
    public static function decodeDigit($digit)
    {
        return strpos(self::ENCODING, $digit);
    }

    /**
     * Calculate the geohash for a Point to a given precision.
     * The precision is measured in geohash characters.
     *
     * @param \BlueX\Geo\Point $point
     * @param integer          $precision
     *
     * @return string
     */
    public static function encode(Point $point, $precision = 8)
    {
        $maxLongitude = 180;
        $minLongitude = -180;
        $maxLatitude  = 90;
        $minLatitude  = -90;

        $hash         = '';
        $longitudeBit = true;

        for ($i = 0; $i < $precision; $i++) {
            $byte = 0;

            for ($j = 0; $j < 5; $j++) {
                if ($longitudeBit) {
                    $mid = ($maxLongitude + $minLongitude) / 2;

                    if ($point->longitude >= $mid) {
                        $bit          = 1;
                        $minLongitude = $mid;
                    } else {
                        $bit          = 0;
                        $maxLongitude = $mid;
                    }
                } else {
                    $mid = ($maxLatitude + $minLatitude) / 2;

                    if ($point->latitude >= $mid) {
                        $bit         = 1;
                        $minLatitude = $mid;
                    } else {
                        $bit         = 0;
                        $maxLatitude = $mid;
                    }
                }

                $byte         = ($byte << 1) | $bit;
                $longitudeBit = !$longitudeBit;
            }

            $hash .= self::encodeDigit($byte);
        }

        return $hash;
    }

    /**
     * Calculate the bounding box for a geohash
     *
     * @param string $hash
     *
     * @return \BlueX\Geo\Box
     */
    public static function decodeBox($hash)
    {
        $northeast = new Point(90, 180);
        $southwest = new Point(-90, -180);

        $count        = strlen($hash);
        $longitudeBit = true;

        for ($i = 0; $i < $count; $i++) {
            $byte = self::decodeDigit($hash[$i]);

            for ($j = 0; $j < 5; $j++) {
                $bit  = ($byte & 0x10) ? 1 : 0;
                $byte = $byte << 1;

                if ($longitudeBit) {
                    $mid = ($northeast->longitude + $southwest->longitude) / 2;

                    if ($bit) {
                        $southwest->longitude = $mid;
                    } else {
                        $northeast->longitude = $mid;
                    }
                } else {
                    $mid = ($northeast->latitude + $southwest->latitude) / 2;

                    if ($bit) {
                        $southwest->latitude = $mid;
                    } else {
                        $northeast->latitude = $mid;
                    }
                }

                $longitudeBit = !$longitudeBit;
            }
        }

        return new Box($northeast, $southwest);
    }

    /**
     * Returns the Point at the center of the geohash box.
     *
     * @param string $hash
     *
     * @return Point
     */
    public static function decode($hash)
    {
        return self::decodeBox($hash)->center();
    }

    /**
     * Returns the geohash immediately following this one.
     * If it's the last geohash, returns null.
     *
     * Examples:
     *      self::increment('38z') returns '390'
     *      self::increment('z') returns null
     *
     * @param string $hash
     *
     * @return string|null
     */
    public static function increment($hash)
    {
        $len  = strlen($hash);
        $char = $hash[$len - 1];
        $code = self::decodeDigit($char);
        $base = ($len > 1) ? substr($hash, 0, $len - 1) : '';

        if ($code < 31) {
            return $base . self::encodeDigit($code + 1);
        } elseif ($len > 1) {
            $base = self::increment($base);

            return $base ? $base . '0' : null;
        } else {
            return null;
        }
    }

    /**
     * Returns a neighboring geohash in a cardinal direction.
     * This function returns null for goehashes north of the north pole or south of the south.
     *
     * @param string $hash
     * @param string $direction one of self::NORTH, self::SOUTH, self::EAST or self::WEST
     *
     * @return null|string
     * @throws \GeoHash\Exception
     */
    public static function neighbor($hash, $direction)
    {
        $precision = strlen($hash);
        $odd       = $precision % 2;

        // odd and even geohash characters map differently, but they are symmetric as per the following mapping
        switch ($direction) {
            case self::NORTH:
                $neighbor = $odd ? self::ODD_NORTH_NEIGHBOR : self::ODD_EAST_NEIGHBOR;
                $border   = $odd ? self::ODD_NORTH_BORDER : self::ODD_EAST_BORDER;
                break;
            case self::SOUTH:
                $neighbor = $odd ? self::ODD_SOUTH_NEIGHBOR : self::ODD_WEST_NEIGHBOR;
                $border   = $odd ? self::ODD_SOUTH_BORDER : self::ODD_WEST_BORDER;
                break;
            case self::EAST:
                $neighbor = $odd ? self::ODD_EAST_NEIGHBOR : self::ODD_NORTH_NEIGHBOR;
                $border   = $odd ? self::ODD_EAST_BORDER : self::ODD_NORTH_BORDER;
                break;
            case self::WEST:
                $neighbor = $odd ? self::ODD_WEST_NEIGHBOR : self::ODD_SOUTH_NEIGHBOR;
                $border   = $odd ? self::ODD_WEST_BORDER : self::ODD_SOUTH_BORDER;
                break;
            default:
                throw new Exception('Unsupported geohash direction, "' . $direction . '"', E_USER_ERROR);
        }

        $char = $hash[$precision - 1];
        $base = substr($hash, 0, $precision - 1);

        if (strpos($border, $char) !== false) {
            // border char
            if ($precision > 1) {
                $base = self::neighbor($base, $direction);

                if ($base == null) {
                    return null;
                }
            } elseif ($direction == self::NORTH || $direction == self::SOUTH) {
                // unable to go north of the north pole or south of the south
                return null;
            }
        }

        $code         = self::decodeDigit($char);
        $neighborChar = $neighbor[$code];

        return $base . $neighborChar;
    }

    /**
     * Returns true if the Point lies within the hash region.
     *
     * @param string           $hash
     * @param \BlueX\Geo\Point $point
     *
     * @return boolean
     */
    public static function contains($hash, Point $point)
    {
        $box = self::decodeBox($hash);

        return ($box->south <= $point->latitude && $point->latitude <= $box->north &&
            $box->west <= $point->longitude && $point->longitude <= $box->east);
    }

    /**
     * Return the quadrant of an ancestor containing this geohash.
     * If no precision is given, the immediant parent will be assumed.
     * We can find out which quadrant of any ancestor up to and including the first character.
     * To determine the global quadrant, choose precision 0.
     *
     * Examples:
     *      '00' is in the southwest quadrant of '0'
     *      'drt2zm8h1t3v' is in the northeast quadrant of 'drt2zm8h1t3'
     *      '9345' is northwest on the globe
     *
     * @param string  $hash      geohash
     * @param integer $precision
     *
     * @return string self::NORTHWEST, self::NORTHEAST, self::SOUTHWEST or self::SOUTHEAST
     */
    public static function quadrant($hash, $precision = null)
    {
        if ($precision >= strlen($hash)) {
            return null;
        }

        $odd      = $precision % 2;
        $quadrant = (int) (self::decodeDigit($hash[$precision]) / 8);

        switch ($quadrant) {
            case 0:
                return self::SOUTHWEST;
            case 1:
                return $odd ? self::SOUTHEAST : self::NORTHWEST;
            case 2:
                return $odd ? self::NORTHWEST : self::SOUTHEAST;
            case 3:
                return self::NORTHEAST;
            default:
                return null;
        }
    }

    /**
     * Cut this geohash into a region half its size.
     *
     * @param string $hash
     * @param string $direction one of self::NORTH, self::SOUTH, self::EAST, self::WEST
     *
     * @return \BlueX\Geo\HashSet
     * @throws \GeoHash\Exception
     */
    public static function halve($hash, $direction)
    {
        $odd = strlen($hash) % 2;

        switch ($direction) {
            case (self::NORTH):
                $set = $odd ? array(array(16, 31)) : array(array(8, 15), array(24, 31));
                break;
            case (self::SOUTH):
                $set = $odd ? array(array(0, 15)) : array(array(0, 7), array(16, 23));
                break;
            case (self::EAST):
                $set = $odd ? array(array(8, 15), array(24, 31)) : array(array(16, 31));
                break;
            case (self::WEST):
                $set = $odd ? array(array(0, 7), array(16, 23)) : array(array(0, 15));
                break;
            default:
                throw new Exception('Illegal halve direction, "' . $direction . '"', E_USER_ERROR);
                break;
        }

        $region = new HashSet();

        foreach ($set as $range) {
            $region->addRange($hash . self::encodeDigit($range[0]), $hash . self::encodeDigit($range[1]));
        }

        return $region;
    }

    /**
     * Reduce this geohash to a region of one quadrant.
     *
     * @param string $hash
     * @param string $direction one of self::NORTHEAST, self::NORTHWEST, self::SOUTHEAST, self::SOUTHWEST
     *
     * @return \BlueX\Geo\HashSet
     * @throws \GeoHash\Exception
     */
    public static function quarter($hash, $direction)
    {
        $odd = strlen($hash) % 2;

        switch ($direction) {
            case (self::NORTHEAST):
                $range = array(24, 31);
                break;
            case (self::NORTHWEST):
                $range = $odd ? array(16, 23) : array(8, 15);
                break;
            case (self::SOUTHEAST):
                $range = $odd ? array(8, 15) : array(16, 23);
                break;
            case (self::SOUTHWEST):
                $range = array(0, 7);
                break;
            default:
                throw new Exception('Illegal quarter direction, "' . $direction . '"', E_USER_ERROR);
                break;
        }

        $set = new HashSet();
        $set->addRange($hash . self::encodeDigit($range[0]), $hash . self::encodeDigit($range[1]));

        return $set;
    }
}
