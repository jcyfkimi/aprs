#include <math.h>

char *my_stpcpy(char *dst, const char *src){
	char *q = dst;
	const char *p = src;
	while (*p) *q++ = *p++; 
	return q; 
} 

char decode_mic_lat(char c) {
	if(c>='0' && c<='9') return c;
	if(c>='A' && c<='J') return c-'A'+'0';
	if(c>='K' && c<='L') return ' ';
	if(c>='P' && c<='Y') return c-'P'+'0';
	if(c=='Z') return ' ';
	return ' ';
}

int checkcall(char*call) {
	char *p;
	if(strlen(call)<5) return 0;
	p=call;
	if(!isdigit(*(p+2))) return 0;
/*
	if(!isupper(*(p+0))) return 0;
	if(!isupper(*(p+1))) return 0;
	if(!isdigit(*(p+2))) return 0;
*/
	while(*p) { 			// allow [A-Z0-9-]
		if( isupper(*p) ||
		    isdigit(*p) ||
		    (*p=='-') 
		) p++;
		else return 0;
	}
	return 1;
}

int checklat(char *s) {
	if(!isdigit(*s)) return 0;
	if(!isdigit(*(s+1)) && (*(s+1)!=' ') ) return 0;
	if(!isdigit(*(s+2)) && (*(s+2)!=' ') ) return 0;
	if(!isdigit(*(s+3)) && (*(s+3)!=' ') ) return 0;
	if(*(s+4)!='.') return 0;
	if(!isdigit(*(s+5)) && (*(s+5)!=' ') ) return 0;
	if(!isdigit(*(s+6)) && (*(s+6)!=' ') ) return 0;
	if(*(s+7)=='N') return 1;
	if(*(s+7)=='S') return 1;
	return 0;
}

int checklon(char *s) {
	if(!isdigit(*s)) return 0;
	if(!isdigit(*(s+1)) && (*(s+1)!=' ') ) return 0;
	if(!isdigit(*(s+2)) && (*(s+2)!=' ') ) return 0;
	if(!isdigit(*(s+3)) && (*(s+3)!=' ') ) return 0;
	if(!isdigit(*(s+4)) && (*(s+4)!=' ') ) return 0;
	if(*(s+5)!='.') return 0;
	if(!isdigit(*(s+6)) && (*(s+6)!=' ') ) return 0;
	if(!isdigit(*(s+7)) && (*(s+7)!=' ') ) return 0;
	if(*(s+8)=='E') return 1;
	if(*(s+8)=='W') return 1;
	return 0;
}

void ToMysql(char *buf, int len)
{	char bufcopy[MAXLEN],sqlbuf[MAXLEN],*end;
	if(len<=10) return;
	if(len>1000)len=1000;
	if(buf[len-1]=='\n') len--;
	if(buf[len-1]=='\r') len--;
	if(buf[len-1]=='\n') len--;
	buf[len]=0;
	strcpy(bufcopy,buf);

	char *call="", *path="", datatype=0, *lat="", *lon="", table=0, symbol=0, *msg="", *p,*s;
	call = buf;
	p=strchr(buf,'>');
	if(p==NULL) return;
	*p=0;
	if(checkcall(call)==0) {
#ifdef DEBUG
		err_msg("skipp call: %s\n",call);
#endif	
		return;
	}
	s=p+1;
	path=s;
        p = strchr(s,':');
	if(p==NULL) return;
	p++; 
	{	// fix PHG04600!2343.06NR12034.80E#NextVOD + TNC-22M Rx-only iGate144.640Mhz,
		// change to !2343.06NR12034.80E# PHG04600 NextVOD + TNC-22M Rx-only iGate144.640Mhz,
		char tmp[8];
		if((strlen(p)>=27) && (memcmp(p,"PHG",3)==0))  {
			memcpy(tmp,p,8);
			memcpy(p,p+8,19);
			memcpy(p+19,tmp,8);
		}
	}
	datatype = *p;
	p++;

	if( datatype == '/' ) {  // change datatype / to !
		datatype = '!'; 
		if( strlen(p)<17 ) 
			goto unknow_msg;
		p+=7;
	} else if( datatype == '@' ) {  // change datatype @ to =
		datatype = '='; 
		if( strlen(p)<17 ) 
			goto unknow_msg;
		p+=7;
	}
	if( (datatype == '=') || (datatype=='!') ) {
		if( (*p=='/') && (strlen(p)>=12)) {			//compressed data  !/BmQnk)&Y[ A
			char S,W;
			float flat,flon;
			table = *p;
			symbol = *(p+9);
			p++;
			flat= 90.0 - ((*p-33.0)*91*91*91 + (*(p+1)-33)*91*91 + (*(p+2)-33)* 91 + *(p+3)-33) / 380926;
			p+=4;
			flon= -180.0 + ((*p-33.0)*91*91*91 + (*(p+1)-33)*91*91 + (*(p+2)-33)* 91 + *(p+3)-33) / 190463;
			if(flat<0) {
				flat = -flat;
				S='S';
			} else
				S='N';
			if(flon<0) {
				flon = -flon;
				W='W';
			} else
				W='E';

			p-=4;  // will rewrite buff 
			lat = p;
			sprintf(lat,"%02.0f%05.2f%c", flat,(flat-floor(flat))*60,S);
			lon = p+10;
			sprintf(lon,"%02.0f%05.2f%c",flon,(flon-floor(flon))*60,W);
			msg = "";
		} else {
			if( strlen(p)<17 ) 
				goto unknow_msg;
			lat = p;
			if(checklat(lat)==0) lat="";
			table=*(p+8);
			*(p+8)=0;
			p+=9;
			lon= p;
			if(checklon(lon)==0) lon="";
			symbol = *(p+9);
			*(p+9)=0;
			p+=10;
			msg=p;
		}
		end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,bufcopy,len);
		end = my_stpcpy(end,"')");
		*end=0;
#ifdef DEBUG
		err_msg("%s\n",sqlbuf);
#endif
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		

		end = my_stpcpy(sqlbuf,"REPLACE INTO lastpacket(tm,`call`,datatype,lat,lon,`table`,symbol,msg) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"')");
		*end=0;
#ifdef DEBUG
		err_msg("%s\n",sqlbuf);
#endif
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		end = my_stpcpy(sqlbuf,"INSERT INTO packetstats VALUES(curdate(),1) ON DUPLICATE KEY UPDATE packets=packets+1");
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		return;
	}

	if( (datatype == '`') || (datatype=='\'')) {    // Mic-E
		if( strlen(p)<8 ) 
			goto unknow_msg;
		if( strlen(path)<6 )
			goto unknow_msg;
		char lat[9];
		lat[0]=decode_mic_lat(*(path));
		lat[1]=decode_mic_lat(*(path+1));
		lat[2]=decode_mic_lat(*(path+2));
		lat[3]=decode_mic_lat(*(path+3));
		lat[4]='.';
		lat[5]=decode_mic_lat(*(path+4));
		lat[6]=decode_mic_lat(*(path+5));
		char c;
		c = *(path+3);
		if ( c>='0' && c<='9' ) lat[7]='S';
		else lat[7]='N';
		lat[8]=0;

		int d;
		d = *p - 28;
		if (*(path+4)>='P' ) d+=100;
		if( d>=180 && d<=189)
			d-=80;
		else if( d>=190 && d<=199)
			d-=190;
		int m;
		m = *(p+1) - 28;
		if(m>=60) m-=60;
	
		int hm;
		hm = *(p+2) - 28;
		
		char lon[10];
		lon[0] = (d/100) + '0';	
		lon[1] =  (d%100)/10 + '0';
		lon[2] =  (d%10) + '0';
		lon[3] = m/10 + '0';
		lon[4] = m%10 + '0';
		lon[5]='.';
		lon[6]=hm/10 + '0';
		lon[7]=hm%10 + '0';
		if(*(path+5)>='P') lon[8]='W';	
		else lon[8]='E';
		lon[9]=0;

		msg=p;
		table=*(p+7);
		symbol = *(p+6);
		p+=8;
		end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`,datatype,lat,lon,`table`,symbol,msg,raw) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,bufcopy,len);
		end = my_stpcpy(end,"')");
		*end=0;
#ifdef DEBUG
		err_msg("%s\n",sqlbuf);
#endif
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		} 
		end = my_stpcpy(sqlbuf,"REPLACE INTO lastpacket(tm,`call`,datatype,lat,lon,`table`,symbol,msg) VALUES(now(),'");
		end += mysql_real_escape_string(mysql,end,call,strlen(call));
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&datatype,1);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lat);
		end = my_stpcpy(end,"','");
		end = my_stpcpy(end,lon);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&table,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,&symbol,1);
		end = my_stpcpy(end,"','");
		end += mysql_real_escape_string(mysql,end,msg,strlen(msg));
		end = my_stpcpy(end,"')");
		*end=0;
#ifdef DEBUG
		err_msg("%s\n",sqlbuf);
#endif
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		end = my_stpcpy(sqlbuf,"INSERT INTO packetstats VALUES(curdate(),1) ON DUPLICATE KEY UPDATE packets=packets+1");
		if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   			err_quit("Failed to insert row, Error: %s\n",
           		mysql_error(mysql));
		}
		return;
	}
unknow_msg:	
	end = my_stpcpy(sqlbuf,"INSERT INTO aprspacket (tm,`call`, raw) VALUES(now(),'");
	end += mysql_real_escape_string(mysql,end,call,strlen(call));
	end = my_stpcpy(end,"','");
	end += mysql_real_escape_string(mysql,end,bufcopy,len);
	end = my_stpcpy(end,"')");
	*end=0;

#ifdef DEBUG
	err_msg("%s\n",sqlbuf);
#endif
	if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   		err_quit("Failed to insert row, Error: %s\n",
           	mysql_error(mysql));
	}
	end = my_stpcpy(sqlbuf,"INSERT INTO packetstats VALUES(curdate(),1) ON DUPLICATE KEY UPDATE packets=packets+1");
	if (mysql_real_query(mysql,sqlbuf,(unsigned int) (end - sqlbuf))) {
   		err_quit("Failed to insert row, Error: %s\n",
       		mysql_error(mysql));
	}
}

