/* aprstcp v1.0 by  james@ustc.edu.cn 2015.12.19

   replay 14580 tcp aprs packet to "china.aprs2.net"
   send all packets to udp
	127.0.0.1 14582
	127.0.0.1 14583
	120.25.100.30 14580
   send packets with "-13" to 114.55.54.60 14580
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

#define PORT 14580

// #define DEBUG 1

char *laddr,*lport,*raddr,*rport;
unsigned long fwd,rfwd;
int r_fd, c_fd;
char scaddr[MAXLEN],sladdr[MAXLEN],sraddr[MAXLEN],srcaddr[MAXLEN];

char * PrintAddr(struct sockaddr *sa)
{
	struct sockaddr_in *sa_in;
	struct sockaddr_in6 *sa_in6;
	static char buf[MAXLEN];
	char buf2[MAXLEN];

	if(sa->sa_family==AF_INET) {
		sa_in = (struct sockaddr_in*)sa;
		snprintf(buf,MAXLEN,"%s:%d",
			inet_ntop(sa_in->sin_family,&sa_in->sin_addr,buf2,MAXLEN),
			ntohs(sa_in->sin_port));
	} else if(sa->sa_family==AF_INET6) {
		sa_in6 = (struct sockaddr_in6 *)sa;
		snprintf(buf,MAXLEN,"%s:%d",
			inet_ntop(sa_in6->sin6_family,&sa_in6->sin6_addr,buf2,MAXLEN),
			ntohs(sa_in6->sin6_port));
	} else snprintf(buf,MAXLEN,"unknow family %d",sa->sa_family);
	return buf;
}

void PrintStats(void)
{
	syslog(LOG_INFO,"%s->%s ",scaddr,sladdr);
	syslog(LOG_INFO,"==> %s->%s\n",srcaddr,sraddr);
	syslog(LOG_INFO,"===> %8lu bytes\n",fwd);
	syslog(LOG_INFO,"<=== %8lu bytes\n",rfwd);

}
void sendudp(char*buf, int len, char *host, int port)
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

void relayaprs(char *buf, int len)
{
        sendudp(buf,len, "120.25.100.30",14580);   // forward to aprs.hellocq.net
        sendudp(buf,len, "127.0.0.1",14582);   // udptolog
        sendudp(buf,len, "127.0.0.1",14583);   // udptomysql
	if(strstr(buf,"-13>"))
		sendudp(buf, len , "114.55.54.60",14580);   // forward -13 to lewei50.comI

}

void Process(int c_fd) 
{	
	fd_set rset;
	struct timeval tv;
	int m,n;
	int max_fd;
	struct sockaddr_in6 sa;
	int salen;
	fwd=rfwd=0;	

	r_fd= Tcp_connect(raddr,rport);
	
	scaddr[0]=sladdr[0]=sraddr[0]=srcaddr[0]=0;
	salen=sizeof(sa);
	if(getpeername(c_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(scaddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	salen=sizeof(sa);
	if(getsockname(c_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(sladdr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	
	salen=sizeof(sa);
	if(getpeername(r_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(sraddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);
	salen=sizeof(sa);
	if(getsockname(r_fd,(struct sockaddr*)&sa,(socklen_t *)&salen)==0) 
		strncpy(srcaddr,PrintAddr((struct sockaddr*)&sa),MAXLEN);

	while (1) {
		FD_ZERO(&rset);
		FD_SET(c_fd, &rset);
		FD_SET(r_fd, &rset);
		max_fd = max(c_fd,r_fd);
		tv.tv_sec = 300;
		tv.tv_usec = 0;

		m = Select (max_fd + 1, &rset, NULL, NULL, &tv);

		if (m == 0) 
			continue;
		
		if (FD_ISSET(r_fd, &rset)) {
			char buffer[MAXLEN];
			n = recv (r_fd, buffer, MAXLEN,0);
			if(n<=0)   {
				PrintStats();
				exit(0);
			}
			if( strstr(buffer,"# javAPRSSrvr") &&
			    strstr(buffer,"T2CHINA 221.231.138.178:14580")) {
				static time_t lastkeep=0;
				time_t curt=time(NULL);
				if( (lastkeep!=0) && (curt-lastkeep < 60*10))	
					continue;
				lastkeep=curt;
			}
			Write(c_fd, buffer, n);
			rfwd+=n;
		}	
		if (FD_ISSET(c_fd, &rset)) {
			static char buffer[MAXLEN];
			static int lastread =0;
			n = recv (c_fd, buffer+lastread, MAXLEN-lastread-1,0);
			if(n<=0)   {
				PrintStats();
				exit(0);		
			}
			buffer[lastread+n]=0;
			Write(r_fd, buffer+lastread, n);  
			fwd+=n;
			char *p,*s;
			n=lastread+n;
			p=buffer;
			while (1) {
				if((p-buffer) >= n) break;
				s=strchr(p,'\n');
				if(s==NULL)  
					s=strchr(p,'\r');
				if(s==NULL)  break;
				relayaprs(p,s-p+1);
				p = s+1;
			}
			if((p-buffer)<n) {
				lastread=n-(p-buffer);
				memcpy(buffer,p,lastread);
			}else lastread=0;
		}
	}
}

void usage()
{
	printf("\naprstcp v1.0 - aprs relay by james@ustc.edu.cn\n");
	printf("\naprstcp x.x.x.x 14580 china.aprs2.net 14580\n\n");
	exit(0);
}

int main(int argc, char *argv[])
{
	int listen_fd;
	int llen;

	signal(SIGCHLD,SIG_IGN);
	if(argc!=5) {
		laddr="0.0.0.0";
		lport="14580";
		raddr="china.aprs2.net";
		rport="14580";
	}else {
		laddr=argv[1]; lport=argv[2];
		raddr=argv[3]; rport=argv[4];
	}
	printf("aprsrelay %s:%s -> %s:%s\n", laddr,lport,raddr,rport);

#ifndef DEBUG
	daemon_init("aprsrelay",LOG_DAEMON);
#endif

	listen_fd = Tcp_listen(laddr,lport,(socklen_t *)&llen);

	while (1) {
		struct sockaddr sa; int slen;
		slen = sizeof(sa);
		c_fd = Accept(listen_fd, &sa, (socklen_t *)&slen);
#ifdef DEBUG
		Process(c_fd);
#else
		if( Fork()==0 ) {
			Close(listen_fd);
			Process(c_fd);
		}
#endif
		Close(c_fd);
	}
}
