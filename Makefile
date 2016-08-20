all: aprstcp aprscmdtcp aprsudp udptoaprs udptomysql udptolog udptolocal aprs.fi.toudp local.toudp local.toaprs gt02 aprstomysql

aprs.fi.toudp: aprs.fi.toudp.c passcode.c
	gcc -o aprs.fi.toudp aprs.fi.toudp.c -Wall
local.toudp: local.toudp.c passcode.c
	gcc -o local.toudp local.toudp.c -Wall
local.toaprs: local.toaprs.c passcode.c
	gcc -o local.toaprs local.toaprs.c -Wall
aprscmdtcp: aprscmdtcp.c
	gcc -g -o aprscmdtcp aprscmdtcp.c	 -Wall  -lmysqlclient -L/usr/lib64/mysql/
aprstcp: aprstcp.c
	gcc -g -o aprstcp aprstcp.c	 -Wall  

gt02: gt02.c
	gcc -g -o gt02 gt02.c	 -Wall -lm
aprsudp: aprsudp.c
	gcc -g -o aprsudp aprsudp.c	 -Wall
udptoaprs: udptoaprs.c passcode.c
	gcc -o udptoaprs udptoaprs.c -Wall
udptolocal: udptolocal.c passcode.c
	gcc -o udptolocal udptolocal.c -Wall
udptomysql: udptomysql.c db.h tomysql.c
	gcc -g -o udptomysql udptomysql.c -Wall -lmysqlclient -L/usr/lib64/mysql/
aprstomysql: aprstomysql.c db.h tomysql.c
	gcc -g -o aprstomysql aprstomysql.c -Wall -lmysqlclient -L/usr/lib64/mysql/

clean:
	rm -f aprsudp.o
