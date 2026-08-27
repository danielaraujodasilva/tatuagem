(()=>{const native=window.setInterval;window.setInterval=(fn,ms,...args)=>(ms===15000||ms===400)?0:native(fn,ms,...args)})();
