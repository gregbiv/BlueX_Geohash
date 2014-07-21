Basic usage
===========

First of all please be sure satisfying requirements described in README.md
You'll need [Composer](http://getcomposer.org/download/) to start.

    $ composer.phar install

Methods of caching interface are described below.

BlueX\Geo\Hash::encode($point, $precision)
---------
Calculate the geohash for a BlueX\Geo\Point to a given precision. The precision is measured in geohash characters.


BlueX\Geo\Hash::decode($hash)
---------
Returns the BlueX\Geo\Point at the center of the geohash box.


BlueX\Geo\Hash::decodeBox($hash)
---------
Calculate the bounding box for a geohash. Returns BlueX\Geo\Box with Northeast and Southwest coordinates


BlueX\Geo\Hash::increment($hash)
---------
Returns the parent geohash

BlueX\Geo\Hash::neighbor($hash, $direction)
---------
Returns a neighboring geohash in a cardinal direction. This function returns null for goehashes north of the north pole or south of the south.

BlueX\Geo\Hash::contains($hash, $point)
---------
Returns true if the BlueX\Geo\Point lies within the hash region.

BlueX\Geo\Hash::quadrant($hash, $precision)
---------
Return the quadrant of an ancestor containing this geohash.

If no precision is given, the immediant parent will be assumed. We can find out which quadrant of any ancestor up to and including the first character.
To determine the global quadrant, choose precision 0.

###Examples:
    '00' is in the southwest quadrant of '0'
    'drt2zm8h1t3v' is in the northeast quadrant of 'drt2zm8h1t3'
    '9345' is northwest on the globe

