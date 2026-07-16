const Ziggy = {"url":"http:\/\/localhost","port":null,"defaults":{},"routes":{"login":{"uri":"login","methods":["GET","HEAD"]},"login.post":{"uri":"login","methods":["POST"]},"logout":{"uri":"logout","methods":["POST"]},"language.switch":{"uri":"language\/{locale}","methods":["GET","HEAD"],"wheres":{"locale":"[a-z]{2}"},"parameters":["locale"]},"dashboard":{"uri":"dashboard","methods":["GET","HEAD"]},"storage.local":{"uri":"storage\/{path}","methods":["GET","HEAD"],"wheres":{"path":".*"},"parameters":["path"]},"storage.local.upload":{"uri":"storage\/{path}","methods":["PUT"],"wheres":{"path":".*"},"parameters":["path"]}}};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
  Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
