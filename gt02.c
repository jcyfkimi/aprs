/* gt02 v1.0 by  james@ustc.edu.cn 2016.05.11
   accept GT02 connection on tcp port 8821
	if IMEI was found in file imei_call.txt, 
		send to udp 127.0.0.1:14580, then go to aprs.fi
	else 
		use GT2UN-9 as call and send to 127.0.0.1:14582, 127.0.0.1:14583 for local display
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
#include <math.h>

#define MAXLEN 16384

// #define DEBUG 1

void dump_pkt(unsigned char *buf, int len)
{
	int i;
	fprintf(stderr,"pkt len=%d:",len);
	for(i=0;i<len;i++) {
		if(i%8==0) 
			fprintf(stderr," ");
		fprintf(stderr,"%02X",*(buf+i));
	}
	fprintf(stderr,"\n");
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

char *imei_call(unsigned char *imei) {  // imei 8 bytes
        FILE *fp;
	static char call[MAXLEN];
	char ibuf[MAXLEN];
	int i;

	for(i=0;i<8;i++) {
		sprintf(call+2*i,"%02X",*(imei+i));
	}
#ifdef DEBUG
	fprintf(stderr,"%s\n",call);
#endif
        fp = fopen("/usr/src/aprs/imei_call.txt","r"); // IMEI 0102030405060708 BG?-YYY
        if (fp==NULL) {
#ifdef DEBUG
                fprintf(stderr, "open imei_call.txt error\n");
#endif
        } else {
        	while(fgets(ibuf,MAXLEN,fp)) {
                	if(strlen(ibuf)<8) continue;
                	if(ibuf[strlen(ibuf)-1]=='\n')
                        	ibuf[strlen(ibuf)-1]=0;
			if(memcmp(ibuf,call,16)==0) {
				fclose(fp);
				strncpy(call,ibuf+17,10);
				return call;
			}
		}
		fclose(fp);
	}
	sprintf(call,"GT2UN-9");
        return call;
}
void processaprs(unsigned char *buf, int len)
{
        char abuf[MAXLEN];
	char *call;
	static time_t last_tm;
	time_t now_tm;
	now_tm = time(NULL);
	if(now_tm-last_tm < 5) {
#ifdef DEBUG
		fprintf(stderr,"packet interval < 5, skip\n");
#endif
		return;
	}
	last_tm = now_tm;

	call=imei_call(buf+5);
	if(call[0]==0) 
		return;
	int n=0,i;
	n = sprintf(abuf,"%s>GT02,TCPIP*:=",call);
	float l;
	l = ((buf[22]*256+buf[23])*256+buf[24])*256+buf[25];
	l = l/30000;
#ifdef DEBUG
	fprintf(stderr,"%f\n",l);
#endif
	n+= sprintf(abuf+n,"%02d%05.2f%c/",(int)(l/60),l-60*((int) (l/60)), (buf[39]&2) == 0? 'S':'N');
	l = ((buf[26]*256+buf[27])*256+buf[28])*256+buf[29];
	l = l/30000;
#ifdef DEBUG
	fprintf(stderr,"%f, %d\n",l,(int)(l/60));
#endif
	n+= sprintf(abuf+n,"%03d%05.2f%c>",(int)(l/60),l-60*((int)(l/60)), (buf[39]&4) == 0? 'W':'E');
	n+= sprintf(abuf+n,"%03d/%03d",buf[31]*256+buf[32],buf[30]);
	n+= sprintf(abuf+n,"IMEI:");
	for(i=6;i<8;i++) 
		n+= sprintf(abuf+n,"%02X",*(buf+5+i));
	n+= sprintf(abuf+n,"\r\n");
#ifdef DEBUG
	fprintf(stderr,"APRS: %s\n",abuf);
#endif
	if(strstr(abuf,"GT2UN-9")==0)  // imei_call
        	sendudp(abuf,n,"127.0.0.1",14580);
	else {
        	sendudp(abuf,n,"127.0.0.1",14582);
        	sendudp(abuf,n,"127.0.0.1",14583);
	}
}

void Process(int c_fd) 
{
        unsigned char buffer[MAXLEN];
        int n;
        int optval;
        socklen_t optlen = sizeof(optval);
        optval = 120;
        Setsockopt(c_fd, SOL_SOCKET, SO_KEEPALIVE, &optval, optlen);
        optval = 3;
        Setsockopt(c_fd, SOL_TCP, TCP_KEEPCNT, &optval, optlen);
        optval = 120;
        Setsockopt(c_fd, SOL_TCP, TCP_KEEPIDLE, &optval, optlen);
        optval = 2;
        Setsockopt(c_fd, SOL_TCP, TCP_KEEPINTVL, &optval, optlen);

        while (1) {
                n = Readn(c_fd, buffer, 3);
                if(n<=0)   {
                        exit(0);
                }
		n += Readn(c_fd, buffer+3, buffer[2]+2);
#ifdef DEBUG
		dump_pkt(buffer,n);
#endif
                buffer[n]=0;
		if( (n>=15) && (buffer[0]==0x68) && (buffer[1]==0x68) && (buffer[15]==0x1a) ) { // heart beat 
			buffer[0]=0x54;	
			buffer[1]=0x68;	
			buffer[2]=0x1A;	
			buffer[3]=0x0d;	
			buffer[4]=0x0a;	
        		Write(c_fd, buffer, 5);
#ifdef DEBUG
			fprintf(stderr,"heart beat packet\n");
			fprintf(stderr,"send back heart beat\n");
#endif
			continue;
		}
		if( (n==42) && (buffer[0]==0x68) && (buffer[1]==0x68) && 
			(buffer[2]==0x25) && 
//			(buffer[3]==0x0) && (buffer[4]==0x0) && 
			(buffer[15]==0x10) ) { // gps status 
#ifdef DEBUG
			fprintf(stderr,"GPS status packet\n");
#endif
                	processaprs(buffer,n);
			continue;
		}
#ifdef DEBUG
		fprintf(stderr,"unknow packer\n");
#endif
        }
}

void usage()
{
	printf("\ngt02 v1.0 - gt02 to aprs by james@ustc.edu.cn\n");
	printf("\ngt02\n\n");
	exit(0);
}

int main(int argc, char *argv[])
{
	int listen_fd;
	int c_fd;
	int llen;

	signal(SIGCHLD,SIG_IGN);

#ifndef DEBUG
	daemon_init("gt02",LOG_DAEMON);
#endif

	listen_fd = Tcp_listen("0.0.0.0","8821",(socklen_t *)&llen);

	while (1) {
		struct sockaddr sa; int slen;
		slen = sizeof(sa);
		c_fd = Accept(listen_fd, &sa, (socklen_t *)&slen);
#ifdef DEBUG
		fprintf(stderr,"get connection:\n");
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
