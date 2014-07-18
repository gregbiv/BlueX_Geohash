<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Chris Johnson
 * @author  Gregory Kornienko <gregbiv@gmail.com>
 * @license MIT
 */

use BlueX\Geo\Box;
use BlueX\Geo\Hash;
use BlueX\Geo\Point;

/**
 * Tests for \BlueX\Geo\Hash
 */
class HashTest extends BaseTest
{
    /**
     * These functions provide raw conversion digit by digit.
     *
     * @see          \BlueX\Geo\Hash::encodeDigit()
     * @see          \BlueX\Geo\Hash::decodeDigit()
     *
     * @dataProvider digitProvider
     *
     * @param integer $number
     * @param string  $digit
     */
    public function testGeocodeEncodeDigitAndDecodeDigit($number, $digit)
    {
        $this->assertEquals($digit, Hash::encodeDigit($number));
        $this->assertEquals($number, Hash::decodeDigit($digit));
    }

    /**
     * @see \BlueX\Geo\Hash::encode()
     * @see \BlueX\Geo\Hash::decode()
     * @see \BlueX\Geo\Hash::decodeBox()
     *
     * @dataProvider encodingProvider
     * @param \BlueX\Geo\Point $point
     * @param integer          $precision
     * @param string           $Hash
     */
    public function testEncodeAndDecodeBox(Point $point, $precision, $Hash)
    {
        // encode the lat/lon and compare the result to the Hash
        if ($precision === null) {
            $result = Hash::encode($point);
        } else {
            $result = Hash::encode($point, $precision);
        }

        $this->assertEquals($Hash, $result);

        // decode the goehash box and make sure it contains the lat/lon
        $box = Hash::decodeBox($Hash);
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
        $decode = Hash::decode($Hash);
        $this->assertLessThan(($dlat / 2) + 0.0001, abs($point->latitude - $decode->latitude));
        $this->assertLessThan(($dlon / 2) + 0.0001, abs($point->longitude - $decode->longitude));
    }

    /**
     * After the testEncodeAndDecodeBox, this test relies on those functions.
     *
     * @see          \BlueX\Geo\Hash::neighbor()
     *
     * @dataProvider HashProvider
     * @param string $Hash
     */
    public function testNeighbors($Hash)
    {
        $this->assertGreaterThan(0, strlen($Hash));

        $box = Hash::decodeBox($Hash);

        $north_hash = Hash::neighbor($Hash, Hash::NORTH);
        $south_hash = Hash::neighbor($Hash, Hash::SOUTH);
        $east_hash  = Hash::neighbor($Hash, Hash::EAST);
        $west_hash  = Hash::neighbor($Hash, Hash::WEST);

        // test the north box adjacency
        if (abs($box->north - 90) < 0.0001) {
            // north edge
            $this->assertNull($north_hash);
        } else {
            $north_box = Hash::decodeBox($north_hash);
            $this->assertEquals($box->east, $north_box->east);
            $this->assertEquals($box->west, $north_box->west);
            $this->assertLessThan(0.0001, $north_box->south - $box->north);
        }

        // test the south box adjacency
        if (abs($box->south + 90) < 0.0001) {
            // north edge
            $this->assertNull($south_hash);
        } else {
            $south_box = Hash::decodeBox($south_hash);
            $this->assertEquals($box->east, $south_box->east);
            $this->assertEquals($box->west, $south_box->west);
            $this->assertLessThan(0.0001, $south_box->north - $box->south);
        }

        // test the east box adjacency
        $east_box = Hash::decodeBox($east_hash);
        $this->assertEquals($box->north, $east_box->north);
        $this->assertEquals($box->south, $east_box->south);

        if (abs($box->east - 180) < 0.0001) {
            // past the international dateline and onto the west edge
            $this->assertLessThan(0.0001, $east_box->west + 180);
        } else {
            $this->assertLessThan(0.0001, $east_box->west - $box->east);
        }

        // test the west box adjacency
        $west_box = Hash::decodeBox($west_hash);
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
     * @see          \BlueX\Geo\Hash::quadrant()
     *
     * @dataProvider HashProvider
     * @param string $Hash
     */
    public function testQuadrant($Hash)
    {
        $len = strlen($Hash);
        $this->assertGreaterThan(0, $len);

        $point = Hash::decode($Hash);

        // test every precision from invalid to global
        for ($precision = $len + 1; $precision >= 0; $precision--) {
            $quadrant = Hash::quadrant($Hash, $precision);
            if ($precision >= $len) {
                // invalid precision, returns null
                $this->assertNull($quadrant);
            } else {
                $box    = ($precision > 0) ? Hash::decodeBox(substr($Hash, 0, $precision)) : new Box(new Point(90, 180), new Point(-90, -180));
                $center = $box->center();

                switch ($quadrant) {
                    case Hash::NORTHWEST:
                        $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                        $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case Hash::NORTHEAST:
                        $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                        $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case Hash::SOUTHWEST:
                        $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                        $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                        break;
                    case Hash::SOUTHEAST:
                        $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                        $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                        break;
                }
            }
        }
    }

    /**
     * @see          \BlueX\Geo\Hash::halve()
     *
     * @dataProvider HashProvider
     * @param string $Hash
     */
    public function testHalve($Hash)
    {
        $north = Hash::neighbor($Hash, Hash::NORTH);
        $south = Hash::neighbor($Hash, Hash::SOUTH);
        $east  = Hash::neighbor($Hash, Hash::EAST);
        $west  = Hash::neighbor($Hash, Hash::WEST);

        $outside_tests = array(Hash::decode($east), Hash::decode($west));

        if ($north) {
            $outside_tests[] = Hash::decode($north);
        }
        if ($south) {
            $outside_tests[] = Hash::decode($south);
        }

        $north_half = Hash::halve($Hash, Hash::NORTH);
        $south_half = Hash::halve($Hash, Hash::SOUTH);
        $east_half  = Hash::halve($Hash, Hash::EAST);
        $west_half  = Hash::halve($Hash, Hash::WEST);

        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($north_half->contains($test));
            $this->assertFalse($south_half->contains($test));
            $this->assertFalse($east_half->contains($test));
            $this->assertFalse($west_half->contains($test));
        }

        // test points from the four quadrants
        $box  = Hash::decodeBox($Hash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;

        $northwest = new Point($box->north - ($dlat / 4), $box->west + ($dlon / 4));
        $northeast = new Point($box->north - ($dlat / 4), $box->east - ($dlon / 4));
        $southwest = new Point($box->south + ($dlat / 4), $box->west + ($dlon / 4));
        $southeast = new Point($box->south + ($dlat / 4), $box->east - ($dlon / 4));

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
        $northwest = new Point($box->north, $box->west);
        $northeast = new Point($box->north, $box->east);
        $southwest = new Point($box->south, $box->west);
        $southeast = new Point($box->south, $box->east);

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
        $center_point = new Point($box->north - ($dlat / 2), $box->west + ($dlon / 2));
        $this->assertTrue($north_half->contains($center_point));
        $this->assertTrue($south_half->contains($center_point));
        $this->assertTrue($east_half->contains($center_point));
        $this->assertTrue($west_half->contains($center_point));
    }

    /**
     * @see          \BlueX\Geo\Hash::quarter()
     *
     * @dataProvider HashProvider
     * @param string $Hash
     */
    public function testQuarter($Hash)
    {
        $north = Hash::neighbor($Hash, Hash::NORTH);
        $south = Hash::neighbor($Hash, Hash::SOUTH);
        $east  = Hash::neighbor($Hash, Hash::EAST);
        $west  = Hash::neighbor($Hash, Hash::WEST);

        $outside_tests = array(Hash::decode($east), Hash::decode($west));

        if ($north) {
            $outside_tests[] = Hash::decode($north);
        }
        if ($south) {
            $outside_tests[] = Hash::decode($south);
        }

        $northwest = Hash::quarter($Hash, Hash::NORTHWEST);
        $northeast = Hash::quarter($Hash, Hash::NORTHEAST);
        $southwest = Hash::quarter($Hash, Hash::SOUTHWEST);
        $southeast = Hash::quarter($Hash, Hash::SOUTHEAST);

        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($northwest->contains($test));
            $this->assertFalse($northeast->contains($test));
            $this->assertFalse($southwest->contains($test));
            $this->assertFalse($southeast->contains($test));
        }

        // test points from the four quadrants
        $box  = Hash::decodeBox($Hash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;

        $northwest_point = new Point($box->north - ($dlat / 4), $box->west + ($dlon / 4));
        $northeast_point = new Point($box->north - ($dlat / 4), $box->east - ($dlon / 4));
        $southwest_point = new Point($box->south + ($dlat / 4), $box->west + ($dlon / 4));
        $southeast_point = new Point($box->south + ($dlat / 4), $box->east - ($dlon / 4));

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
        $northwest_point = new Point($box->north, $box->west);
        $northeast_point = new Point($box->north, $box->east);
        $southwest_point = new Point($box->south, $box->west);
        $southeast_point = new Point($box->south, $box->east);

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
        $center_point = new Point($box->north - ($dlat / 2), $box->west + ($dlon / 2));
        $this->assertTrue($northwest->contains($center_point));
        $this->assertTrue($northeast->contains($center_point));
        $this->assertTrue($southwest->contains($center_point));
        $this->assertTrue($southeast->contains($center_point));
    }
}
