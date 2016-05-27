/* aprs.tomysql.c v1.0 by  james@ustc.edu.cn 2015.12.19

   connect to china.aprs2.net. tcp 14580 port, login filter p/B p/VR2
   store all packets to mysql database

*/

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <time.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <linux/if.h>
#include <linux/if_ether.h>
#include <linux/if_packet.h>
#include "sock.h"
#include <ctype.h>

// #define DEBUG 1

#define MAXLEN 16384

#include "db.h"

#include "tomysql.c"

#include "passcode.c"

void sendudp(char *buf, int len, char *host, int port)
{
        struct sockaddr_in si_other;
        int s, slen=sizeof(si_other);
        int l;
#ifdef DEBUG
        fprintf(stderr,"send to %s,",host);
#endif
        if ((s=socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP))==-1) {
                fprintf(stderr,"socket error");
                return;
        }
        memset((char *) &si_other, 0, sizeof(si_other));
        si_other.sin_family = AF_INET;
        si_other.sin_port = htons(port);
        if (inet_aton(host, &si_other.sin_addr)==0) {
                fprintf(stderr, "inet_aton() failed\n");
                close(s);
                return;
        }
        l = sendto(s, buf, len, 0, (const struct sockaddr *)&si_other, slen);
#ifdef DEBUG
        fprintf(stderr,"%d\n",l);
#endif
        close(s);
}


void Process(char *server, char *call) 
{	
	char buf[MAXLEN];
	int r_fd;
	int n;
	int optval;
   	socklen_t optlen = sizeof(optval);
	r_fd= Tcp_connect(server,"14580");
	optval = 1;
	Setsockopt(r_fd, SOL_SOCKET, SO_KEEPALIVE, &optval, optlen);
	optval = 3;
	Setsockopt(r_fd, SOL_TCP, TCP_KEEPCNT, &optval, optlen);
	optval = 2;
	Setsockopt(r_fd, SOL_TCP, TCP_KEEPIDLE, &optval, optlen);
	optval = 2;
	Setsockopt(r_fd, SOL_TCP, TCP_KEEPINTVL, &optval, optlen);

	snprintf(buf,MAXLEN,"user %s pass %d vers aprs.fi.toudp 1.5 filter p/B p/VR2 p/9\r\n",call,passcode(call));
	Write(r_fd, buf, strlen(buf));

	while (1) {
		n = Readline(r_fd, buf, MAXLEN);
		if(n<=0)   {
			exit(0);
		}
		if(buf[0]=='#') continue;
		buf[n]=0;
		if(strstr(buf,"-13>") && (strstr(buf,",BG6CQ:")==0))
                	sendudp(buf, n , "114.55.54.60",14580);   // forward -13 to lewei50.comI
		ToMysql(buf,n);
	}
}

void usage()
{
	printf("\naprstomysql v1.0 - aprs relay by james@ustc.edu.cn\n");
	printf("\naprstomysql [ x.x.x.x CALL ]\n\n");
	exit(0);
}

int main(int argc, char *argv[])
{
	char *call="BG6DA-4";
	char *server="china.aprs2.net";
	signal(SIGCHLD,SIG_IGN);
	if(argc==3) {
		server=argv[1];
		call=argv[2];
	} else if(argc!=1) {
		usage();
		exit(0);
	}

#ifndef DEBUG
	daemon_init("aprstomysql",LOG_DAEMON);
	while(1) {
                int pid;
                pid=fork();
                if(pid==0) // i am child, will do the job
                        break;
                else if(pid==-1) // error
                        exit(0);
                else
                        wait(NULL); // i am parent, wait for child
                sleep(2);  // if child exit, wait 2 second, and rerun
        }
#endif
	mysql=connectdb();

//	mysql_query(mysql,"use china");
	Process(server, call);
	return(0);
}
