(()=>{const native=window.setInterval;window.setInterval=(fn,ms,...args)=>ms===15000?0:native(fn,ms,...args)})();
