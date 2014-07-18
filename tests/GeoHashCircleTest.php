<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Chris Johnson
 * @author  Gregory Kornienko <gregbiv@gmail.com>
 * @license MIT
 */
use BlueX\Geo\Hash;
use BlueX\Geo\Point;
use BlueX\Geo\HashCircle;

/**
 * Tests for \BlueX\Geo\HashCircle
 */
class HashCircleTest extends BaseTest
{
    /**
     * This test isn't yet working for edge cases,
     * expanding to the original precision or to the minimum precision.
     * Perhaps those expansions don't make much sense.
     *
     * @dataProvider HashProvider
     * @see          \BlueX\Geo\HashCircle::expand()
     */
    public function testCircle($Hash)
    {
        $circle      = new HashCircle($Hash);
        $center      = Hash::decode($Hash);
        $precision   = strlen($Hash);
        $last_radius = 0;
        // test all precisions up to 0
        do {
            $radius = $circle->max_radius() / Point::EARTH_RADIUS;

            $circle->expand($Hash, $precision);

            // the center point should be in the set
            $this->assertTrue($circle->contains($center));

            // the following tests only work for points away from the poles where radiuses expand usefully
            if ($radius > 0) {
                $this->assertGreaterThan($last_radius, $radius);

                // all points box size x 1 distance from the center should be in the set
                $this->assertTrue($circle->contains(new Point(min($center->latitude + $radius, 90), $this->normalizeLongitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new Point(min($center->latitude + $radius, 90), $this->normalizeLongitude($center->longitude - $radius))));
                $this->assertTrue($circle->contains(new Point(max($center->latitude - $radius, -90), $this->normalizeLongitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new Point(max($center->latitude - $radius, -90), $this->normalizeLongitude($center->longitude - $radius))));

                $last_radius = $radius;
            }
        } while ($circle->expand());
    }
}
