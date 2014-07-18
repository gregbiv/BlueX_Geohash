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

/**
 * Main class with tests.
 */
abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The digits used by Hash to form the base-32 number system.
     * @return array
     */
    public static function digitProvider()
    {
        return array(
            array(0, '0'),
            array(1, '1'),
            array(2, '2'),
            array(3, '3'),
            array(4, '4'),
            array(5, '5'),
            array(6, '6'),
            array(7, '7'),
            array(8, '8'),
            array(9, '9'),
            array(10, 'b'),
            array(11, 'c'),
            array(12, 'd'),
            array(13, 'e'),
            array(14, 'f'),
            array(15, 'g'),
            array(16, 'h'),
            array(17, 'j'),
            array(18, 'k'),
            array(19, 'm'),
            array(20, 'n'),
            array(21, 'p'),
            array(22, 'q'),
            array(23, 'r'),
            array(24, 's'),
            array(25, 't'),
            array(26, 'u'),
            array(27, 'v'),
            array(28, 'w'),
            array(29, 'x'),
            array(30, 'y'),
            array(31, 'z')
        );
    }

    /**
     * Tests for testing the encoding.
     * The default precision is 8 characters.
     * @return array of arrays(latitude, longitude, precision, Hash)
     */
    public static function encodingProvider()
    {
        // test 12 character Hashes, edge cases and internal places
        $encodings = array(
            array(new Point(0, 0), 12, 's00000000000'),
            array(new Point(45, 90), 12, 'y00000000000'),
            array(new Point(45, -90), 12, 'f00000000000'),
            array(new Point(-45, 90), 12, 'q00000000000'),
            array(new Point(-45, -90), 12, '600000000000'),
            array(new Point(90, 180), 12, 'zzzzzzzzzzzz'),
            array(new Point(90, -180), 12, 'bpbpbpbpbpbp'),
            array(new Point(-90, 180), 12, 'pbpbpbpbpbpb'),
            array(new Point(-90, -180), 12, '000000000000'),
            array(new Point(42.350072, -71.047656), 12, 'drt2zm8ej9eg'),
            array(new Point(38.898632, -77.036541), 12, 'dqcjqcr8yqxd'),
            array(new Point(-23.442503, -58.443832), 12, '6ey6wh6t808q'),
            array(new Point(47.516231, 14.550072), 12, 'u26q7454172n'),
            array(new Point(19.856270, 102.495496), 12, 'w78buqdznjj0'),
        );
        foreach ($encodings as $test) {
            $encodings[] = array($test[0], 1, substr($test[2], 0, 1)); // test single character Hashes
            $encodings[] = array($test[0], 2, substr($test[2], 0, 2)); // test two character Hashes
            $encodings[] = array($test[0], 5, substr($test[2], 0, 5)); // test five character Hashes
            $encodings[] = array($test[0], null, substr($test[2], 0, 8)); // test default eight character Hashes
        }

        return $encodings;
    }

    /**
     * A bunch of Hashes for testing neighbors, contains and other stuff.
     * @return array
     */
    public static function HashProvider()
    {
        $Hashes = array(
            array('s00000000000'),
            array('y00000000000'),
            array('f00000000000'),
            array('q00000000000'),
            array('600000000000'),
            array('zzzzzzzzzzzz'),
            array('bpbpbpbpbpbp'),
            array('pbpbpbpbpbpb'),
            array('000000000000'),
            array('drt2zm8ej9eg'),
            array('dqcjqcr8yqxd'),
            array('6ey6wh6t808q'),
            array('u26q7454172n'),
            array('w78buqdznjj0')
        );

        // add some random tests
        for ($i = 0; $i < 3; $i++) {
            $Hashes[] = array(
                Hash::encode(new Point(mt_rand(-90000, 90000) / 1000, mt_rand(-180000, 180000) / 1000, 12))
            );
        }

        foreach ($Hashes as $test) {
            $Hashes[] = array(substr($test[0], 0, 1)); // test single character Hashes
            $Hashes[] = array(substr($test[0], 0, 2)); // test two character Hashes
            $Hashes[] = array(substr($test[0], 0, 5)); // test five character Hashes
            $Hashes[] = array(substr($test[0], 0, 8)); // test eight character Hashes
        }

        return $Hashes;
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
