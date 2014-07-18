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

/**
 * Main class with tests.
 */
abstract class Base extends \PHPUnit_Framework_TestCase
{
    /**
     * The digits used by geohash to form the base-32 number system.
     * @return array
     */
    public static function digitProvider()
    {
        return array(
            [0, '0'],
            [1, '1'],
            [2, '2'],
            [3, '3'],
            [4, '4'],
            [5, '5'],
            [6, '6'],
            [7, '7'],
            [8, '8'],
            [9, '9'],
            [10, 'b'],
            [11, 'c'],
            [12, 'd'],
            [13, 'e'],
            [14, 'f'],
            [15, 'g'],
            [16, 'h'],
            [17, 'j'],
            [18, 'k'],
            [19, 'm'],
            [20, 'n'],
            [21, 'p'],
            [22, 'q'],
            [23, 'r'],
            [24, 's'],
            [25, 't'],
            [26, 'u'],
            [27, 'v'],
            [28, 'w'],
            [29, 'x'],
            [30, 'y'],
            [31, 'z']
        );
    }

    /**
     * Tests for testing the encoding.
     * The default precision is 8 characters.
     * @return array of arrays(latitude, longitude, precision, geohash)
     */
    public static function encodingProvider()
    {
        // test 12 character geohashes, edge cases and internal places
        $encodings = [
            [new GeoPoint(0, 0), 12, 's00000000000'],
            [new GeoPoint(45, 90), 12, 'y00000000000'],
            [new GeoPoint(45, -90), 12, 'f00000000000'],
            [new GeoPoint(-45, 90), 12, 'q00000000000'],
            [new GeoPoint(-45, -90), 12, '600000000000'],
            [new GeoPoint(90, 180), 12, 'zzzzzzzzzzzz'],
            [new GeoPoint(90, -180), 12, 'bpbpbpbpbpbp'],
            [new GeoPoint(-90, 180), 12, 'pbpbpbpbpbpb'],
            [new GeoPoint(-90, -180), 12, '000000000000'],
            [new GeoPoint(42.350072, -71.047656), 12, 'drt2zm8ej9eg'],
            [new GeoPoint(38.898632, -77.036541), 12, 'dqcjqcr8yqxd'],
            [new GeoPoint(-23.442503, -58.443832), 12, '6ey6wh6t808q'],
            [new GeoPoint(47.516231, 14.550072), 12, 'u26q7454172n'],
            [new GeoPoint(19.856270, 102.495496), 12, 'w78buqdznjj0'],
        ];
        foreach ($encodings as $test) {
            $encodings[] = [$test[0], 1, substr($test[2], 0, 1)]; // test single character geohashes
            $encodings[] = [$test[0], 2, substr($test[2], 0, 2)]; // test two character geohashes
            $encodings[] = [$test[0], 5, substr($test[2], 0, 5)]; // test five character geohashes
            $encodings[] = [$test[0], null, substr($test[2], 0, 8)]; // test default eight character geohashes
        }

        return $encodings;
    }

    /**
     * A bunch of geohashes for testing neighbors, contains and other stuff.
     * @return array
     */
    public static function geohashProvider()
    {
        $geohashes = [
            ['s00000000000'],
            ['y00000000000'],
            ['f00000000000'],
            ['q00000000000'],
            ['600000000000'],
            ['zzzzzzzzzzzz'],
            ['bpbpbpbpbpbp'],
            ['pbpbpbpbpbpb'],
            ['000000000000'],
            ['drt2zm8ej9eg'],
            ['dqcjqcr8yqxd'],
            ['6ey6wh6t808q'],
            ['u26q7454172n'],
            ['w78buqdznjj0']
        ];
        // add some random tests
        for ($i = 0; $i < 3; $i++) {
            $geohashes[] = [
                GeoHash::encode(new GeoPoint(mt_rand(-90000, 90000) / 1000, mt_rand(-180000, 180000) / 1000, 12))
            ];
        }
        foreach ($geohashes as $test) {
            $geohashes[] = [substr($test[0], 0, 1)]; // test single character geohashes
            $geohashes[] = [substr($test[0], 0, 2)]; // test two character geohashes
            $geohashes[] = [substr($test[0], 0, 5)]; // test five character geohashes
            $geohashes[] = [substr($test[0], 0, 8)]; // test eight character geohashes
        }

        return $geohashes;
    }

    /**
     * Returns the longitude as a number between -180 and 180.
     * The longitude entered may be the product of an equation
     * that could put it outside of this range.  The value given
     * will be a place on the earth, just expressed strangely.
     *
     * @param float $longitude
     *
     * @return float
     */
    public function normalizeLongitude($longitude)
    {
        while ($longitude > 180) {
            $longitude -= 360;
        }
        while ($longitude < -180) {
            $longitude += 360;
        }

        return $longitude;
    }
}