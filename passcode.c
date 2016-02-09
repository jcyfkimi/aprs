int passcode(char *callin)
{       int i=0;
	char call[40];
	strncpy(call,callin,30);
        unsigned int hash = 0x73e2;
        while(call[i]) {
                call[i]=toupper(call[i]);
                call[i+1]=toupper(call[i+1]);
                hash ^= call[i]<< 8;
                hash ^= call[i+1];
                if( call[i+1] == 0 ) break;
                if( call[i+1] == '-' ) break;
                i+=2;
        }
        hash = hash & 0x7fff;
	return hash;
}
