#!/bin/sh

PHPDOCDIR=/home/gtk/pear/PHPDoc
PHPDOC=$PHPDOCDIR/phpdoc
$PHPDOC -s $PWD -d $PWD/docs -t $PHPDOCDIR -e default -f
