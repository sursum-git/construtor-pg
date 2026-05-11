const http = require("http");
const fs = require("fs");
const path = require("path");
const { request: proxyRequest } = require("http");

const root = path.resolve(__dirname, "..");
const port = Number(process.argv[2] || 8765);
const apiProxy = process.env.CRUD_ENGINE_API_PROXY || "http://127.0.0.1:8000";

const mime = {
  ".html": "text/html; charset=utf-8",
  ".js": "application/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".png": "image/png",
  ".map": "application/json; charset=utf-8"
};

const server = http.createServer((request, response) => {
  if (request.url && request.url.startsWith("/api/")) {
    proxyApi(request, response);
    return;
  }

  const url = new URL(request.url, "http://127.0.0.1:" + port);
  const requestedPath = url.pathname === "/" ? "/index.html" : decodeURIComponent(url.pathname);
  const filePath = path.normalize(path.join(root, requestedPath));

  if (!filePath.startsWith(root)) {
    response.writeHead(403);
    response.end("Forbidden");
    return;
  }

  fs.readFile(filePath, (error, data) => {
    if (error) {
      response.writeHead(404);
      response.end("Not found");
      return;
    }

    response.writeHead(200, {
      "Content-Type": mime[path.extname(filePath)] || "application/octet-stream"
    });
    response.end(data);
  });
});

server.listen(port, "127.0.0.1", () => {
  console.log("CRUD demo: http://127.0.0.1:" + port + "/index.html");
  console.log("Program builder: http://127.0.0.1:" + port + "/program-builder.html");
  console.log("API proxy: " + apiProxy);
});

function proxyApi(request, response) {
  const target = new URL(request.url, apiProxy);
  const options = {
    hostname: target.hostname,
    port: target.port || 80,
    path: target.pathname + target.search,
    method: request.method,
    headers: request.headers
  };

  const proxy = proxyRequest(options, (proxyResponse) => {
    response.writeHead(proxyResponse.statusCode || 502, proxyResponse.headers);
    proxyResponse.pipe(response, { end: true });
  });

  proxy.on("error", () => {
    response.writeHead(502);
    response.end("API proxy error");
  });

  request.pipe(proxy, { end: true });
}
