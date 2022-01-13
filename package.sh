#!/bin/bash

VERSION=`cat VERSION`
FILENAME="ama-flightpath-${VERSION}.tar.gz"

TMP_DIR="ama-flightpath"
REF='HEAD'

if [ -d "$TMP_DIR" ]; then
  echo "Removing old build directory."
  rm -rf $TMP_DIR;
fi

mkdir $TMP_DIR

git archive $REF | tar -x -C $TMP_DIR

cd $TMP_DIR && composer install --prefer-dist -o --no-dev
cd ../
tar -czvf $FILENAME $TMP_DIR

rm -rf $TMP_DIR
