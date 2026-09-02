#!/bin/bash

mydir=$( cd ${0%/*} ; pwd )
cd $mydir

defport=42173
definstance=${mydir##*/}
INSTANCENAME=${1:-$definstance}
INITPORT=${2:-$defport}
PUBLICINIT=${3:-C0nclave}
PRIVATEINIT=${4:-$RANDOM}

echo "instancename: $INSTANCENAME"
echo "port: $INITPORT"
echo "publicinit: $PUBLICINIT"
echo "privateinit: $PRIVATEINIT"

printf "PORT=$INITPORT \n" > .env

echo $INSTANCENAME > _instance

RANDOM=$PRIVATEINIT

# flag generation ... deberia estar en un sitio comun
R=$RANDOM
dlen=32
d=$( printf "GENERA-%s-%d\n" $PUBLICINIT $R | openssl md5 | cut -d ' ' -f 2,2 | head -c $dlen )
flag=$( printf "%s{%s}" "$PUBLICINIT" "$d" )
###

echo $flag >_flag.txt
