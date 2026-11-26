document.addEventListener('DOMContentLoaded',()=>{
 const e=document.getElementById('dashboard-pago-data'); if(!e||!window.Chart)return;
 const d=JSON.parse(e.textContent), keys=Object.keys(d.estados), colores=['#d8a233','#4996b2','#118b75','#d45562','#8799a7','#a78965','#6576b5','#aa77ae'];
 const fmt=v=>new Intl.NumberFormat('es-PE',{style:'currency',currency:'PEN'}).format(v);
 function chart(id,type,labels,values,colors,money=false){const c=document.getElementById(id);if(!c)return;new Chart(c,{type,data:{labels,datasets:[{label:money?'Importe aprobado':'Pagos',data:values.map(Number),backgroundColor:colors,borderColor:'#087f8c',borderRadius:5,tension:.25}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:type==='doughnut',position:'bottom'},tooltip:{callbacks:{label:ctx=>money?fmt(ctx.raw):String(ctx.raw)}}},...(type==='doughnut'?{}:{scales:{y:{beginAtZero:true,ticks:money?{callback:fmt}:{precision:0}}}})}});}
 chart('dashboardEstadosPago','doughnut',keys.map(k=>d.etiquetas[k]),keys.map(k=>d.estados[k]),colores);
 const m=Object.fromEntries(d.modalidades.map(r=>[r.modalidad,r.monto]));chart('dashboardModalidadPago','bar',['Presencial','Virtual'],[m.presencial||0,m.virtual||0],['#087f8c','#4996b2'],true);
 chart('dashboardMesPago','line',d.meses.map(r=>r.mes),d.meses.map(r=>r.monto),'#087f8c',true);
});
