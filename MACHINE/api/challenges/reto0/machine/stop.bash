#!/bin/bash

mydir=$( cd ${0%/*} ; pwd )
cd $mydir
INSTANCENAME=$( cat _instance )

docker rm -f $INSTANCENAME


