#!/bin/bash

# Camera info
IP="192.168.1.164"
USER="AAHH-201421-CFFEF"
PASS="01234567aA"
HTTP_PORT="80"
RTSP_PORT="554"
RTMP_PORT="1935"

echo "Testing various streaming URLs for IP camera at $IP"
echo "=================================================="
echo ""

# RTMP URLs
echo "RTMP URLs to test:"
echo "rtmp://$IP:$RTMP_PORT/live"
echo "rtmp://$IP:$RTMP_PORT/stream"
echo "rtmp://$IP:$RTMP_PORT/live/stream"
echo "rtmp://$IP:$RTMP_PORT/live/test"
echo "rtmp://$IP:$RTMP_PORT/livestream"
echo "rtmp://$IP:$RTMP_PORT/av0"
echo "rtmp://$IP:$RTMP_PORT/ch0"
echo "rtmp://$IP:$RTMP_PORT/ch1"
echo "rtmp://$IP:$RTMP_PORT/cam/realmonitor"
echo "rtmp://$USER:$PASS@$IP:$RTMP_PORT/live"
echo "rtmp://$USER:$PASS@$IP:$RTMP_PORT/stream"
echo ""

# HTTP/HLS URLs
echo "HTTP/HLS URLs to test:"
echo "http://$IP:$HTTP_PORT/live/test/index.m3u8"
echo "http://$IP:$HTTP_PORT/stream/live.m3u8"
echo "http://$IP:$HTTP_PORT/live.m3u8"
echo "http://$IP:$HTTP_PORT/stream.m3u8"
echo "http://$IP:$HTTP_PORT/livestream.m3u8"
echo "http://$IP:$HTTP_PORT/video.m3u8"
echo "http://$IP:$HTTP_PORT/h264/ch1/main/av_stream"
echo "http://$IP:$HTTP_PORT/mjpeg/video.mjpg"
echo "http://$IP:$HTTP_PORT/video.cgi"
echo "http://$IP:$HTTP_PORT/videostream.cgi"
echo "http://$USER:$PASS@$IP:$HTTP_PORT/live/test/index.m3u8"
echo "http://$USER:$PASS@$IP:$HTTP_PORT/stream.m3u8"
echo ""

# RTSP URLs
echo "RTSP URLs to test:"
echo "rtsp://$IP:$RTSP_PORT/live"
echo "rtsp://$IP:$RTSP_PORT/stream"
echo "rtsp://$IP:$RTSP_PORT/live/stream"
echo "rtsp://$IP:$RTSP_PORT/h264"
echo "rtsp://$IP:$RTSP_PORT/av0"
echo "rtsp://$IP:$RTSP_PORT/ch0"
echo "rtsp://$IP:$RTSP_PORT/ch1"
echo "rtsp://$IP:$RTSP_PORT/cam/realmonitor"
echo "rtsp://$IP:$RTSP_PORT/Streaming/Channels/101"
echo "rtsp://$IP:$RTSP_PORT/11"
echo "rtsp://$USER:$PASS@$IP:$RTSP_PORT/live"
echo "rtsp://$USER:$PASS@$IP:$RTSP_PORT/stream"
echo "rtsp://$USER:$PASS@$IP:$RTSP_PORT/h264"
echo "rtsp://$USER:$PASS@$IP:$RTSP_PORT/ch0"
echo ""

echo "Testing HTTP endpoints with curl..."
echo "=================================================="

# Test some HTTP endpoints
for path in "" "/live/test/index.m3u8" "/stream.m3u8" "/live.m3u8" "/video.cgi" "/videostream.cgi"; do
    echo -n "Testing http://$IP:$HTTP_PORT$path ... "
    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 "http://$IP:$HTTP_PORT$path" 2>/dev/null)
    if [ "$response" = "200" ]; then
        echo "✓ SUCCESS (200 OK)"
    elif [ "$response" = "401" ]; then
        echo "✓ Found but needs auth (401)"
        # Test with credentials
        echo -n "  Testing with credentials... "
        response=$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 -u "$USER:$PASS" "http://$IP:$HTTP_PORT$path" 2>/dev/null)
        if [ "$response" = "200" ]; then
            echo "✓ SUCCESS with auth (200 OK)"
        else
            echo "✗ Failed ($response)"
        fi
    else
        echo "✗ Failed ($response)"
    fi
done
