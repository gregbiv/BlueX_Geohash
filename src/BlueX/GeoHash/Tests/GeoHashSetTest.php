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

use BlueX\GeoHash\Source\GeoHash;
use BlueX\GeoHash\Source\GeoHashSet;
use BlueX\GeoHash\Source\GeoPoint;

/**
 * Tests for \BlueX\GeoHash\Source\GeoHashSet
 */
class GeoHashSetTest extends Base
{
    /**
     * This test constructs the test points and ranges in and around the geohash.
     *
     * @see          \BlueX\GeoHash\Source\GeoHashSet::contains()
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testContains($geohash)
    {
        $north = GeoHash::neighbor($geohash, GeoHash::NORTH);
        $south = GeoHash::neighbor($geohash, GeoHash::SOUTH);
        $east  = GeoHash::neighbor($geohash, GeoHash::EAST);
        $west  = GeoHash::neighbor($geohash, GeoHash::WEST);

        $outside_tests = array($east, $west);

        if ($north) {
            $outside_tests[] = $north;
        }
        if ($south) {
            $outside_tests[] = $south;
        }

        // test points outside the geohash box
        foreach ($outside_tests as $test) {
            for ($i = 0; $i < 32; $i++) {
                $point = GeoHash::decode($test . GeoHash::encodeDigit($i));
                $this->assertFalse(GeoHash::contains($geohash, $point));
            }
        }

        // test points inside the box
        for ($i = 0; $i < 32; $i++) {
            $point = GeoHash::decode($geohash . GeoHash::encodeDigit($i));
            $this->assertTrue(GeoHash::contains($geohash, $point));
        }
        // and the four edge cases (all corners)
        $box = GeoHash::decodeBox($geohash);
        $this->assertTrue(GeoHash::contains($geohash, new GeoPoint($box->north, $box->west)));
        $this->assertTrue(GeoHash::contains($geohash, new GeoPoint($box->north, $box->east)));
        $this->assertTrue(GeoHash::contains($geohash, new GeoPoint($box->south, $box->west)));
        $this->assertTrue(GeoHash::contains($geohash, new GeoPoint($box->south, $box->east)));

        // make a geohash set and test points inside and out
        $set = new GeoHashSet();
        $set->addRange($geohash . GeoHash::encodeDigit(8), $geohash . GeoHash::encodeDigit(23));

        // test points outside the set
        foreach ($outside_tests as $test) {
            $point = GeoHash::decode($test);
            $this->assertFalse($set->contains($point));
        }

        // test closer points outside the set
        for ($i = 0; $i < 8; $i++) {
            $point = GeoHash::decode($geohash . GeoHash::encodeDigit($i));
            $this->assertFalse($set->contains($point));
        }

        for ($i = 24; $i < 32; $i++) {
            $point = GeoHash::decode($geohash . GeoHash::encodeDigit($i));
            $this->assertFalse($set->contains($point));
        }

        // test points inside the set
        for ($i = 8; $i < 24; $i++) {
            $point = GeoHash::decode($geohash . GeoHash::encodeDigit($i));
            $this->assertTrue($set->contains($point));
        }

        // make a compound geohash set and test points inside and out
        $set = new GeoHashSet();
        $set->add($geohash);
        $set->add($east);

        // test points outside the set
        $outside_tests = array(GeoHash::neighbor($east, GeoHash::EAST), $west);

        if ($north) {
            $outside_tests[] = $north;
        }

        if ($south) {
            $outside_tests[] = $south;
        }

        foreach ($outside_tests as $test) {
            $point = GeoHash::decode($test);
            $this->assertFalse($set->contains($point));
        }

        // test points inside the set
        $point = GeoHash::decode($geohash);
        $this->assertTrue($set->contains($point));
        $point = GeoHash::decode($east);
        $this->assertTrue($set->contains($point));
    }
}