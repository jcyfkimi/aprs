/* local.toaprs.c v1.0 by  james@ustc.edu.cn 2015.12.19

   connect to 127.0.0.1 tcp 14580 port, login filter p/BA p/BD p/BG p/BH p/BR
   send all packets to tcp
	china.aprs2.net 14580

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

#define MAXLEN 16384

// #define DEBUG 1

#define PORT 14580

int r_fd;
int aprs_fd;

#include "passcode.c"

void Process(char*server,char *call) 
{	
	char buffer[MAXLEN];
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

	snprintf(buffer,MAXLEN,"user %s pass %d vers aprsfwd 1.5 filter p/B\r\n",call,passcode(call));
	Write(r_fd, buffer, strlen(buffer));

	aprs_fd= Tcp_connect("china.aprs2.net","14580");
	optval = 1;
	Setsockopt(aprs_fd, SOL_SOCKET, SO_KEEPALIVE, &optval, optlen);
	optval = 3;
	Setsockopt(aprs_fd, SOL_TCP, TCP_KEEPCNT, &optval, optlen);
	optval = 200;
	Setsockopt(aprs_fd, SOL_TCP, TCP_KEEPIDLE, &optval, optlen);
	optval = 200;
	Setsockopt(aprs_fd, SOL_TCP, TCP_KEEPINTVL, &optval, optlen);

	snprintf(buffer,MAXLEN,"user %s pass %d vers aprsfwd 1.5 \r\n",call,passcode(call));
	Write(aprs_fd, buffer, strlen(buffer));
	while (1) {
		n = Readline(r_fd, buffer, MAXLEN);
		if(n<=0)   {
			exit(0);
		}
		if(buffer[0]=='#') continue;
		buffer[n]=0;
#ifdef	DEBUG
	fprintf(stderr,"r %s", buffer);
#endif
		Write(aprs_fd, buffer, n);
	}
}

void usage()
{
	printf("\nlocal.toaprs v1.0 - aprs relay by james@ustc.edu.cn\n");
	printf("\nlocal.toaprs x.x.x.x CALL\n\n");
	exit(0);
}

int main(int argc, char *argv[])
{
	char *call="BG5DNS-13";
	char *server="127.0.0.1";
	signal(SIGCHLD,SIG_IGN);
	if(argc==3) {
		server=argv[1];
		call=argv[2];
	} else if(argc!=1) {
		printf("local.toaprs server call\n");
		exit(0);
	}

#ifndef DEBUG
	daemon_init("local.toaprs",LOG_DAEMON);
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

	Process(server,call);
	return(0);
}
