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
use BlueX\GeoHash\Source\GeoPoint;
use BlueX\GeoHash\Source\GeoHashCircle;

/**
 * Tests for \BlueX\GeoHash\Source\GeoHashCircle
 */
class GeoHashCircleTest extends Base
{
    /**
     * This test isn't yet working for edge cases,
     * expanding to the original precision or to the minimum precision.
     * Perhaps those expansions don't make much sense.
     *
     * @dataProvider geohashProvider
     * @see          \BlueX\GeoHash\Source\GeoHashCircle::expand()
     */
    public function testCircle($geohash)
    {
        $circle      = new GeoHashCircle($geohash);
        $center      = GeoHash::decode($geohash);
        $precision   = strlen($geohash);
        $last_radius = 0;
        // test all precisions up to 0
        do {
            $radius = $circle->max_radius() / GeoPoint::EARTH_RADIUS;

            $circle->expand($geohash, $precision);

            // the center point should be in the set
            $this->assertTrue($circle->contains($center));

            // the following tests only work for points away from the poles where radiuses expand usefully
            if ($radius > 0) {
                $this->assertGreaterThan($last_radius, $radius);

                // all points box size x 1 distance from the center should be in the set
                $this->assertTrue($circle->contains(new GeoPoint(min($center->latitude + $radius, 90), $this->normalizeLongitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new GeoPoint(min($center->latitude + $radius, 90), $this->normalizeLongitude($center->longitude - $radius))));
                $this->assertTrue($circle->contains(new GeoPoint(max($center->latitude - $radius, -90), $this->normalizeLongitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new GeoPoint(max($center->latitude - $radius, -90), $this->normalizeLongitude($center->longitude - $radius))));

                $last_radius = $radius;
            }
        } while ($circle->expand());
    }
}