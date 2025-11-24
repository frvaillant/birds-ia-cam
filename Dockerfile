FROM debian:stable

RUN apt-get update && \
    apt-get install -y build-essential libpcre2-dev libssl-dev zlib1g-dev wget unzip

# Get Nginx source
RUN wget http://nginx.org/download/nginx-1.25.4.tar.gz && \
    tar -zxvf nginx-1.25.4.tar.gz && \
    rm nginx-1.25.4.tar.gz

# Get RTMP module source
RUN wget https://github.com/arut/nginx-rtmp-module/archive/refs/heads/master.zip && \
    unzip master.zip && \
    rm master.zip

# Compile Nginx with RTMP module
RUN cd nginx-1.25.4 && \
    ./configure --with-http_ssl_module --add-module=../nginx-rtmp-module-master --with-pcre && \
    make -j$(nproc) && \
    make install

COPY nginx.conf /usr/local/nginx/conf/nginx.conf

EXPOSE 1935
EXPOSE 8080

CMD ["/usr/local/nginx/sbin/nginx", "-g", "daemon off;"]
