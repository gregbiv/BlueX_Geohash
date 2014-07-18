<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Chris Johnson
 * @author  Gregory Kornienko <gregbiv@gmail.com>
 * @license MIT
 */
namespace GeoHash\Tests;

use BlueX\GeoHash\Source\GeoBox;
use BlueX\GeoHash\Source\GeoHash;
use BlueX\GeoHash\Source\GeoPoint;

/**
 * Tests for \BlueX\GeoHash\Source\GeoHash
 */
class GeoHashTest extends Base
{
    /**
     * These functions provide raw conversion digit by digit.
     *
     * @see          \BlueX\GeoHash\Source\GeoHash::encodeDigit()
     * @see          \BlueX\GeoHash\Source\GeoHash::decodeDigit()
     *
     * @dataProvider digitProvider
     *
     * @param integer $number
     * @param string  $digit
     */
    public function testGeocodeEncodeDigitAndDecodeDigit($number, $digit)
    {
        $this->assertEquals($digit, GeoHash::encodeDigit($number));
        $this->assertEquals($number, GeoHash::decodeDigit($digit));
    }

    /**
     * @see \BlueX\GeoHash\Source\GeoHash::encode()
     * @see \BlueX\GeoHash\Source\GeoHash::decode()
     * @see \BlueX\GeoHash\Source\GeoHash::decodeBox()
     *
     * @dataProvider encodingProvider
     * @param \BlueX\GeoHash\Source\GeoPoint $point
     * @param integer                  $precision
     * @param string                   $geohash
     */
    public function testEncodeAndDecodeBox(GeoPoint $point, $precision, $geohash)
    {
        // encode the lat/lon and compare the result to the geohash
        if ($precision === null) {
            $result = GeoHash::encode($point);
        } else {
            $result = GeoHash::encode($point, $precision);
        }

        $this->assertEquals($geohash, $result);

        // decode the goehash box and make sure it contains the lat/lon
        $box = GeoHash::decodeBox($geohash);
        $this->assertGreaterThanOrEqual($point->latitude, $box->north);
        $this->assertGreaterThanOrEqual($point->longitude, $box->east);
        $this->assertLessThanOrEqual($point->latitude, $box->south);
        $this->assertLessThanOrEqual($point->longitude, $box->west);

        // compare the box size to the precision
        // the slices are the number of times we half the map, starting with the prime meridian (0 longitude)
        $slices = ($precision ? $precision : 8) * 5 / 2;
        $dlat   = 180 / (1 << floor($slices));
        $dlon   = 360 / (1 << ceil($slices));
        $this->assertLessThan(0.0001, abs($box->north - $box->south - $dlat));
        $this->assertLessThan(0.0001, abs($box->east - $box->west - $dlon));

        // decode the hash, and make sure it's reasonably close to the original lat/lon
        $decode = GeoHash::decode($geohash);
        $this->assertLessThan(($dlat / 2) + 0.0001, abs($point->latitude - $decode->latitude));
        $this->assertLessThan(($dlon / 2) + 0.0001, abs($point->longitude - $decode->longitude));
    }

    /**
     * After the testEncodeAndDecodeBox, this test relies on those functions.
     *
     * @see          \BlueX\GeoHash\Source\GeoHash::neighbor()
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testNeighbors($geohash)
    {
        $this->assertGreaterThan(0, strlen($geohash));

        $box = GeoHash::decodeBox($geohash);

        $north_hash = GeoHash::neighbor($geohash, GeoHash::NORTH);
        $south_hash = GeoHash::neighbor($geohash, GeoHash::SOUTH);
        $east_hash  = GeoHash::neighbor($geohash, GeoHash::EAST);
        $west_hash  = GeoHash::neighbor($geohash, GeoHash::WEST);

        // test the north box adjacency
        if (abs($box->north - 90) < 0.0001) {
            // north edge
            $this->assertNull($north_hash);
        } else {
            $north_box = GeoHash::decodeBox($north_hash);
            $this->assertEquals($box->east, $north_box->east);
            $this->assertEquals($box->west, $north_box->west);
            $this->assertLessThan(0.0001, $north_box->south - $box->north);
        }

        // test the south box adjacency
        if (abs($box->south + 90) < 0.0001) {
            // north edge
            $this->assertNull($south_hash);
        } else {
            $south_box = GeoHash::decodeBox($south_hash);
            $this->assertEquals($box->east, $south_box->east);
            $this->assertEquals($box->west, $south_box->west);
            $this->assertLessThan(0.0001, $south_box->north - $box->south);
        }

        // test the east box adjacency
        $east_box = GeoHash::decodeBox($east_hash);
        $this->assertEquals($box->north, $east_box->north);
        $this->assertEquals($box->south, $east_box->south);

        if (abs($box->east - 180) < 0.0001) {
            // past the international dateline and onto the west edge
            $this->assertLessThan(0.0001, $east_box->west + 180);
        } else {
            $this->assertLessThan(0.0001, $east_box->west - $box->east);
        }

        // test the west box adjacency
        $west_box = GeoHash::decodeBox($west_hash);
        $this->assertEquals($box->north, $west_box->north);
        $this->assertEquals($box->south, $west_box->south);

        if (abs($box->west + 180) < 0.0001) {
            // past the international dateline and onto the east edge
            $this->assertLessThan(0.0001, $west_box->east - 180);
        } else {
            $this->assertLessThan(0.0001, $west_box->east - $box->west);
        }
    }

    /**
     * @see          \BlueX\GeoHash\Source\GeoHash::quadrant()
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testQuadrant($geohash)
    {
        $len = strlen($geohash);
        $this->assertGreaterThan(0, $len);

        $point = GeoHash::decode($geohash);

        // test every precision from invalid to global
        for ($precision = $len + 1; $precision >= 0; $precision--) {
            $quadrant = GeoHash::quadrant($geohash, $precision);
            if ($precision >= $len) {
                // invalid precision, returns null
                $this->assertNull($quadrant);
            } else {
                $box    = ($precision > 0) ? GeoHash::decodeBox(substr($geohash, 0, $precision)) : new GeoBox(new GeoPoint(90, 180), new GeoPoint(-90, -180));
                $center = $box->center();

                switch ($quadrant) {
                    case GeoHash::NORTHWEST:
                        $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                        $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case GeoHash::NORTHEAST:
                        $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                        $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case GeoHash::SOUTHWEST:
                        $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                        $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case GeoHash::SOUTHEAST:
                        $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                        $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                        break;
                }
            }
        }
    }

    /**
     * @see          \BlueX\GeoHash\Source\GeoHash::halve()
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testHalve($geohash)
    {
        $north = GeoHash::neighbor($geohash, GeoHash::NORTH);
        $south = GeoHash::neighbor($geohash, GeoHash::SOUTH);
        $east  = GeoHash::neighbor($geohash, GeoHash::EAST);
        $west  = GeoHash::neighbor($geohash, GeoHash::WEST);

        $outside_tests = array(GeoHash::decode($east), GeoHash::decode($west));

        if ($north) {
            $outside_tests[] = GeoHash::decode($north);
        }
        if ($south) {
            $outside_tests[] = GeoHash::decode($south);
        }

        $north_half = GeoHash::halve($geohash, GeoHash::NORTH);
        $south_half = GeoHash::halve($geohash, GeoHash::SOUTH);
        $east_half  = GeoHash::halve($geohash, GeoHash::EAST);
        $west_half  = GeoHash::halve($geohash, GeoHash::WEST);

        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($north_half->contains($test));
            $this->assertFalse($south_half->contains($test));
            $this->assertFalse($east_half->contains($test));
            $this->assertFalse($west_half->contains($test));
        }

        // test points from the four quadrants
        $box  = GeoHash::decodeBox($geohash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;

        $northwest = new GeoPoint($box->north - ($dlat / 4), $box->west + ($dlon / 4));
        $northeast = new GeoPoint($box->north - ($dlat / 4), $box->east - ($dlon / 4));
        $southwest = new GeoPoint($box->south + ($dlat / 4), $box->west + ($dlon / 4));
        $southeast = new GeoPoint($box->south + ($dlat / 4), $box->east - ($dlon / 4));

        $this->assertTrue($north_half->contains($northwest));
        $this->assertTrue($north_half->contains($northeast));
        $this->assertFalse($north_half->contains($southwest));
        $this->assertFalse($north_half->contains($southeast));

        $this->assertFalse($south_half->contains($northwest));
        $this->assertFalse($south_half->contains($northeast));
        $this->assertTrue($south_half->contains($southwest));
        $this->assertTrue($south_half->contains($southeast));

        $this->assertFalse($east_half->contains($northwest));
        $this->assertTrue($east_half->contains($northeast));
        $this->assertFalse($east_half->contains($southwest));
        $this->assertTrue($east_half->contains($southeast));

        $this->assertTrue($west_half->contains($northwest));
        $this->assertFalse($west_half->contains($northeast));
        $this->assertTrue($west_half->contains($southwest));
        $this->assertFalse($west_half->contains($southeast));

        // these points are from the four corners
        $northwest = new GeoPoint($box->north, $box->west);
        $northeast = new GeoPoint($box->north, $box->east);
        $southwest = new GeoPoint($box->south, $box->west);
        $southeast = new GeoPoint($box->south, $box->east);

        $this->assertTrue($north_half->contains($northwest));
        $this->assertTrue($north_half->contains($northeast));
        $this->assertFalse($north_half->contains($southwest));
        $this->assertFalse($north_half->contains($southeast));

        $this->assertFalse($south_half->contains($northwest));
        $this->assertFalse($south_half->contains($northeast));
        $this->assertTrue($south_half->contains($southwest));
        $this->assertTrue($south_half->contains($southeast));

        $this->assertFalse($east_half->contains($northwest));
        $this->assertTrue($east_half->contains($northeast));
        $this->assertFalse($east_half->contains($southwest));
        $this->assertTrue($east_half->contains($southeast));

        $this->assertTrue($west_half->contains($northwest));
        $this->assertFalse($west_half->contains($northeast));
        $this->assertTrue($west_half->contains($southwest));
        $this->assertFalse($west_half->contains($southeast));

        // test the center
        $center_point = new GeoPoint($box->north - ($dlat / 2), $box->west + ($dlon / 2));
        $this->assertTrue($north_half->contains($center_point));
        $this->assertTrue($south_half->contains($center_point));
        $this->assertTrue($east_half->contains($center_point));
        $this->assertTrue($west_half->contains($center_point));
    }

    /**
     * @see          \BlueX\GeoHash\Source\GeoHash::quarter()
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testQuarter($geohash)
    {
        $north = GeoHash::neighbor($geohash, GeoHash::NORTH);
        $south = GeoHash::neighbor($geohash, GeoHash::SOUTH);
        $east  = GeoHash::neighbor($geohash, GeoHash::EAST);
        $west  = GeoHash::neighbor($geohash, GeoHash::WEST);

        $outside_tests = array(GeoHash::decode($east), GeoHash::decode($west));

        if ($north) {
            $outside_tests[] = GeoHash::decode($north);
        }
        if ($south) {
            $outside_tests[] = GeoHash::decode($south);
        }

        $northwest = GeoHash::quarter($geohash, GeoHash::NORTHWEST);
        $northeast = GeoHash::quarter($geohash, GeoHash::NORTHEAST);
        $southwest = GeoHash::quarter($geohash, GeoHash::SOUTHWEST);
        $southeast = GeoHash::quarter($geohash, GeoHash::SOUTHEAST);

        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($northwest->contains($test));
            $this->assertFalse($northeast->contains($test));
            $this->assertFalse($southwest->contains($test));
            $this->assertFalse($southeast->contains($test));
        }

        // test points from the four quadrants
        $box  = GeoHash::decodeBox($geohash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;

        $northwest_point = new GeoPoint($box->north - ($dlat / 4), $box->west + ($dlon / 4));
        $northeast_point = new GeoPoint($box->north - ($dlat / 4), $box->east - ($dlon / 4));
        $southwest_point = new GeoPoint($box->south + ($dlat / 4), $box->west + ($dlon / 4));
        $southeast_point = new GeoPoint($box->south + ($dlat / 4), $box->east - ($dlon / 4));

        $this->assertTrue($northwest->contains($northwest_point));
        $this->assertFalse($northwest->contains($northeast_point));
        $this->assertFalse($northwest->contains($southwest_point));
        $this->assertFalse($northwest->contains($southeast_point));

        $this->assertFalse($northeast->contains($northwest_point));
        $this->assertTrue($northeast->contains($northeast_point));
        $this->assertFalse($northeast->contains($southwest_point));
        $this->assertFalse($northeast->contains($southeast_point));

        $this->assertFalse($southwest->contains($northwest_point));
        $this->assertFalse($southwest->contains($northeast_point));
        $this->assertTrue($southwest->contains($southwest_point));
        $this->assertFalse($southwest->contains($southeast_point));

        $this->assertFalse($southeast->contains($northwest_point));
        $this->assertFalse($southeast->contains($northeast_point));
        $this->assertFalse($southeast->contains($southwest_point));
        $this->assertTrue($southeast->contains($southeast_point));

        // test the corners
        $northwest_point = new GeoPoint($box->north, $box->west);
        $northeast_point = new GeoPoint($box->north, $box->east);
        $southwest_point = new GeoPoint($box->south, $box->west);
        $southeast_point = new GeoPoint($box->south, $box->east);

        $this->assertTrue($northwest->contains($northwest_point));
        $this->assertFalse($northwest->contains($northeast_point));
        $this->assertFalse($northwest->contains($southwest_point));
        $this->assertFalse($northwest->contains($southeast_point));

        $this->assertFalse($northeast->contains($northwest_point));
        $this->assertTrue($northeast->contains($northeast_point));
        $this->assertFalse($northeast->contains($southwest_point));
        $this->assertFalse($northeast->contains($southeast_point));

        $this->assertFalse($southwest->contains($northwest_point));
        $this->assertFalse($southwest->contains($northeast_point));
        $this->assertTrue($southwest->contains($southwest_point));
        $this->assertFalse($southwest->contains($southeast_point));

        $this->assertFalse($southeast->contains($northwest_point));
        $this->assertFalse($southeast->contains($northeast_point));
        $this->assertFalse($southeast->contains($southwest_point));
        $this->assertTrue($southeast->contains($southeast_point));

        // test the center
        $center_point = new GeoPoint($box->north - ($dlat / 2), $box->west + ($dlon / 2));
        $this->assertTrue($northwest->contains($center_point));
        $this->assertTrue($northeast->contains($center_point));
        $this->assertTrue($southwest->contains($center_point));
        $this->assertTrue($southeast->contains($center_point));
    }
}