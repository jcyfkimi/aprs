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
#include <ctype.h>
#include "sock.h"

//#define DEBUG 1

#define MAXLEN 16384

void Process(int u_fd, int r_fd) 
{	
	fd_set rset;
	char buff[MAXLEN];
	struct timeval tv;
	int m,n;
	int max_fd;

	while (1) {
		FD_ZERO(&rset);
		FD_SET(u_fd, &rset);
		FD_SET(r_fd, &rset);
		max_fd = max(u_fd,r_fd);
		tv.tv_sec = 300;
		tv.tv_usec = 0;

		m = Select (max_fd + 1, &rset, NULL, NULL, &tv);

		if (m == 0) 
			continue;
		
		if (FD_ISSET(r_fd, &rset)) {
			n = recv (r_fd, buff, MAXLEN,0);
			if((n<=0) &&(errno==EINTR)) continue;
			if(n<=0)   {
				err_sys("recv get %d from tcp server\n",n);
				exit(0);
			}
			buff[n]=0;
#ifdef DEBUG
			fprintf(stderr,"S: %s",buff);
#endif
		}	
		if (FD_ISSET(u_fd, &rset)) {
			n = recv (u_fd, buff, MAXLEN,0);
			if(n<0)   {
				err_sys("recv get %d from udp client\n",n);
				exit(0);		
			}
			if(n==0) continue;
			buff[n]=0;
#ifdef DEBUG
			fprintf(stderr,"C: %s",buff);
#endif
			Write(r_fd, buff, n);
		}
	}
}

int passcode(char *call)
{       int i=0;
        unsigned int hash = 0x73e2;
        while(call[i]) {
                call[i]=toupper(call[i]);
                call[i+1]=toupper(call[i+1]);
                hash ^= call[i]<< 8;
                hash ^= call[i+1];
                if( call[i+1] == 0 ) break;
                i+=2;
        }
        hash = hash & 0x7fff;
	return hash;
}

int main(int argc, char *argv[])
{
	int r_fd,u_fd;
	int llen;
	char buf[MAXLEN];

	signal(SIGCHLD,SIG_IGN);

	if(argc!=2) {
		fprintf(stderr,"usage:  aprstcp your_call_sign\n");
		exit(0);
	}

#ifndef DEBUG
	daemon_init("aprstcp",LOG_DAEMON);
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
	err_msg("starting\n");
	u_fd = Udp_server("127.0.0.1","14581",(socklen_t *)&llen);

	r_fd = Tcp_connect("china.aprs2.net","14580");
	snprintf(buf,MAXLEN,"user %s pass %d vers aprsrelay 13 filter r/31.83/117.29/1\r\n",
		argv[1], passcode(argv[1]));
	Write(r_fd, buf, strlen(buf));
#ifdef DEBUG
	fprintf(stderr,"C: %s",buf);
	fprintf(stderr,"u_fd=%d, r_fd=%d\n",u_fd,r_fd);
#endif
	Process(u_fd,r_fd);
	return 0;
}
