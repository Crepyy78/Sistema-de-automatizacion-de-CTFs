#!/bin/bash

mydir=$( cd ${0%/*} ; pwd )
cd $mydir
INSTANCENAME=$( cat _instance )
RETO=$( cat _reto )

source .env

cat _flag.txt >_flag/flag.txt

docker run -d -p $PORT:8080 -v $mydir/_flag:/flag.tmp/ --name $INSTANCENAME $RETO
