"use strict";var T=require("querystring");var P=require("crypto");function u(e,...t){e.loggingEnabled&&console.log(t)}var F="webp",q=(r=>(r.FIT="fit",r.CROP="crop",r.STRETCH="stretch",r))(q||{}),E=["jpg","png","webp"],O=["top-left","top-center","top-right","center-left","center-center","center-right","bottom-left","bottom-center","bottom-right"];function d(e,t){return Number.isInteger(parseInt(e))?parseInt(e):t}function _(e){return e.headers.accept?e.headers.accept[0].value:""}function j(e){let t=e.match(/_(\d+|AUTO)x(\d+|AUTO)_(fit|crop|stretch)_([a-z]+-[a-z]+)_?(\d+)?/);return t?t[0]:void 0}function C(e,t,n){if(!t.v)return!1;let r=n.verifySecret,o=e.uri.substring(1);o=$(o,n);let i=e.querystring.split("&v=");return Object.keys(t).length>1&&i.length===2?(0,P.createHmac)("sha256",r).update(`${o}?${i[0]}`).digest("hex")===i[1]:!1}function w(e,t,n){let r=e.uri,o=_(e),i=r.match(/(.*)\/(.*)\.(.*)$/);if(!i||i.length<3)return null;let s=i[1];s.charAt(0)==="/"&&(s=s.substring(1));let f=i[2],c=i[3],a,l=c;e.headers["x-flux-source-filename"]&&(a=e.headers["x-flux-source-filename"][0].value);let h=j(s),x=$(s.replace(`/${h}`,""),n),g=x.split("/").filter(p=>p),b=n.sources.find(p=>{let m=g[0];return m===n.rootPrefix&&(m=g[1]),p.handle===m});if(!b)return u(n,"Unable to parse source",x),null;let S=v(...g.slice(1));t.f&&E.includes(t.f)?l=t.f:n.acceptWebp&&o.includes(F)&&(l=F),!a&&c!==l&&(a=`${f}.${c}`);let y=U(t,l,n);return y?{prefix:s,fileName:f,extension:l,manipulations:y,source:b,sourcePath:S,sourceFilename:a,transformPathSegment:h}:null}function U(e,t,n){if(!e.mode||!e.w&&!e.h)return u(n,"Request must contain a mode and a width or height"),null;let r={mode:Object.values(q).includes(e.mode)?e.mode:"fit",width:"AUTO",height:"AUTO",position:"center-center"};if(r.width=d(e.w,r.width),r.height=d(e.h,r.height),e.pos&&O.includes(e.pos)&&(r.position=e.pos),e.q){let o=d(e.q,0);o>0&&(r.quality=o)}else t=="jpg"?r.quality=n.jpegQuality:t=="webp"&&(r.quality=n.webpQuality);return r}function R(e){let t=e.manipulations,n=`_${t.width}x${t.height}_${t.mode}_${t.position}`;return t.quality&&(n+=`_${t.quality}`),v(e.prefix,n,`${e.fileName}.${e.extension}`)}function v(...e){return e.filter(t=>t&&t.length>0).join("/")}function $(e,t){return e.startsWith(`/${t.rootPrefix}`)||e.startsWith(`${t.rootPrefix}/`)?e.slice(t.rootPrefix.length+1):e}var Q={loggingEnabled:!0,rootPrefix:"Flux",sources:[],verifyQuery:!0,verifySecret:"",cachedEnabled:!0,bucket:"",region:"",jpegQuality:80,webpQuality:80,acceptWebp:!0};var A=(e,t,n)=>{let r=e.Records[0].cf.request,o=Q;if(global&&global.fluxConfig)o=Object.assign({},o,global.fluxConfig);else return u(o,"Forwarding request, no config accessible"),n(null,r);u(o,"Parsing request",r.uri,r.querystring);let i=(0,T.parse)(r.querystring);if(o.verifyQuery&&!C(r,i,o))return u(o,"Forwarding request, query verification failed"),n(null,r);let s=w(r,i,o);return s?(r.uri="/"+R(s),u(o,"Modifying path to",r.uri),s.sourceFilename&&(r.headers["x-flux-source-filename"]=[{key:"X-Flux-Source-Filename",value:s.sourceFilename}]),n(null,r)):(u(o,"Forwarding request, unable to parse"),n(null,r))};exports.handler=A;
/*!
 * Flux
 * Copyright(c) Chris Dyer
 */
//# sourceMappingURL=index.js.map
