/*
 * =====================================================================================
 *
 *       Filename:  user_http.h
 *
 *    Description:  
 *
 *        Version:  1.0
 *        Created:  01/11/2018 04:02:44 PM
 *       Revision:  none
 *       Compiler:  gcc
 *
 *         Author:  YOUR NAME (), 
 *   Organization:  
 *
 * =====================================================================================
 */

#ifndef HTTPCLIENT_H
#define HTTPCLIENT_H

// #include <espmissingincludes.h> // This can remove some warnings depending on your project setup. It is safe to remove this line.

#define HTTP_STATUS_GENERIC_ERROR  -1   // In case of TCP or DNS error the callback is called with this status.
#define BUFFER_SIZE_MAX            5000 // Size of http responses that will cause an error.

/*
 * "full_response" is a string containing all response headers and the response body.
 * "response_body and "http_status" are extracted from "full_response" for convenience.
 *
 * A successful request corresponds to an HTTP status code of 200 (OK).
 * More info at http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
 */
typedef void (* http_callback)(char * response_body, int http_status, char * response_headers, int body_size);

/*
 * Download a web page from its URL.
 * Try:
 * http_get("http://wtfismyip.com/text", http_callback_example);
 */
void http_get(const char * url, const char * headers, http_callback user_callback);

/*
 * Post data to a web form.
 * The data should be encoded as application/x-www-form-urlencoded.
 * Try:
 * http_post("http://httpbin.org/post", "first_word=hello&second_word=world", http_callback_example);
 */
void http_post(const char * url, const char * post_data, const char * headers, http_callback user_callback);

/*
 * Call this function to skip URL parsing if the arguments are already in separate variables.
 */
void http_raw_request(const char * hostname, int port, bool secure, const char * path, const char * post_data, const char * headers, http_callback user_callback);

/*
 * http callback处理函数.
 */
void http_response_callback(char * response_body, int http_status, char * response_headers, int body_size);

void user_http_req(const char * hostname, int port, bool secure,
    const char * path, const char * post_data, const char * headers);

char* http_parse_result(const char*resp);

#endif