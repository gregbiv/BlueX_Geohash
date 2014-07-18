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
use BlueX\Geo\HashSet;
use BlueX\Geo\Point;

/**
 * Tests for \BlueX\Geo\HashSet
 */
class HashSetTest extends BaseTest
{
    /**
     * This test constructs the test points and ranges in and around the Hash.
     *
     * @see          \BlueX\Geo\HashSet::contains()
     *
     * @dataProvider HashProvider
     * @param string $Hash
     */
    public function testContains($Hash)
    {
        $north = Hash::neighbor($Hash, Hash::NORTH);
        $south = Hash::neighbor($Hash, Hash::SOUTH);
        $east  = Hash::neighbor($Hash, Hash::EAST);
        $west  = Hash::neighbor($Hash, Hash::WEST);

        $outside_tests = array($east, $west);

        if ($north) {
            $outside_tests[] = $north;
        }
        if ($south) {
            $outside_tests[] = $south;
        }

        // test points outside the Hash box
        foreach ($outside_tests as $test) {
            for ($i = 0; $i < 32; $i++) {
                $point = Hash::decode($test . Hash::encodeDigit($i));
                $this->assertFalse(Hash::contains($Hash, $point));
            }
        }

        // test points inside the box
        for ($i = 0; $i < 32; $i++) {
            $point = Hash::decode($Hash . Hash::encodeDigit($i));
            $this->assertTrue(Hash::contains($Hash, $point));
        }
        // and the four edge cases (all corners)
        $box = Hash::decodeBox($Hash);
        $this->assertTrue(Hash::contains($Hash, new Point($box->north, $box->west)));
        $this->assertTrue(Hash::contains($Hash, new Point($box->north, $box->east)));
        $this->assertTrue(Hash::contains($Hash, new Point($box->south, $box->west)));
        $this->assertTrue(Hash::contains($Hash, new Point($box->south, $box->east)));

        // make a Hash set and test points inside and out
        $set = new HashSet();
        $set->addRange($Hash . Hash::encodeDigit(8), $Hash . Hash::encodeDigit(23));

        // test points outside the set
        foreach ($outside_tests as $test) {
            $point = Hash::decode($test);
            $this->assertFalse($set->contains($point));
        }

        // test closer points outside the set
        for ($i = 0; $i < 8; $i++) {
            $point = Hash::decode($Hash . Hash::encodeDigit($i));
            $this->assertFalse($set->contains($point));
        }

        for ($i = 24; $i < 32; $i++) {
            $point = Hash::decode($Hash . Hash::encodeDigit($i));
            $this->assertFalse($set->contains($point));
        }

        // test points inside the set
        for ($i = 8; $i < 24; $i++) {
            $point = Hash::decode($Hash . Hash::encodeDigit($i));
            $this->assertTrue($set->contains($point));
        }

        // make a compound Hash set and test points inside and out
        $set = new HashSet();
        $set->add($Hash);
        $set->add($east);

        // test points outside the set
        $outside_tests = array(Hash::neighbor($east, Hash::EAST), $west);

        if ($north) {
            $outside_tests[] = $north;
        }

        if ($south) {
            $outside_tests[] = $south;
        }

        foreach ($outside_tests as $test) {
            $point = Hash::decode($test);
            $this->assertFalse($set->contains($point));
        }

        // test points inside the set
        $point = Hash::decode($Hash);
        $this->assertTrue($set->contains($point));
        $point = Hash::decode($east);
        $this->assertTrue($set->contains($point));
    }
}
