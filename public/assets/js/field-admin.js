/* ═══════════════════════════════════════════════════
   FIELD & COMMUNICATION (Projects, Attendance, Leave, Announcements)
   Loaded before app.js. These pages fetch their own data from the API.
═══════════════════════════════════════════════════ */

const MAP_TILES='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_CENTER=[24.8607,67.0011];
const LOADING_ROW=(cols)=>`<tr class="empty-row"><td colspan="${cols}">Loading…</td></tr>`;
const EMPTY_ROW=(cols,msg)=>`<tr class="empty-row"><td colspan="${cols}">${msg}</td></tr>`;

const remotePages={};
const REMOTE_LOADERS={projects:fetchProjects,attendance:fetchAttendance,attreport:fetchAttendanceReport,leaves:fetchLeaves,announcements:fetchAnnouncements};

function remoteSlot(r){return remotePages[r]||(remotePages[r]={data:null,stale:true,busy:false});}
function invalidateRemote(r){remoteSlot(r).stale=true;}
function ensureRemote(r){
  const slot=remoteSlot(r);
  if(!slot.stale||slot.busy)return;
  slot.busy=true;
  REMOTE_LOADERS[r]().then(data=>{slot.data=data;slot.stale=false;slot.busy=false;if(route===r)render();});
}

function makeMap(el,lat,lng,zoom){
  const m=L.map(el).setView([lat,lng],zoom);
  L.tileLayer(MAP_TILES,{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(m);
  return m;
}
function fmtTime(iso,tz){
  if(!iso)return '—';
  return new Date(iso).toLocaleString('en-GB',{timeZone:tz||undefined,day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',hour12:false});
}
function fmtDuration(sec){
  const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60);
  return `${h}h ${String(m).padStart(2,'0')}m`;
}
function timeAgo(iso){
  if(!iso)return '—';
  const s=(Date.now()-Date.parse(iso))/1000;
  if(s<60)return 'just now';
  if(s<3600)return `${Math.floor(s/60)} min ago`;
  if(s<86400)return `${Math.floor(s/3600)} h ago`;
  return fmtTime(iso);
}
function lateBadge(minutes,late){
  if(minutes===null||minutes===undefined)return '—';
  return late?`<span class="badge badge-expense">${fmtDuration(minutes*60)} late</span>`:'<span class="badge badge-income">On time</span>';
}
function timezones(){
  return typeof Intl.supportedValuesOf==='function'?Intl.supportedValuesOf('timeZone'):['Asia/Karachi','UTC'];
}

/* ── PROJECTS ── */
let pf={q:'',status:'All'};
let pickMap=null,pickMarker=null,pickCircle=null;

async function fetchProjects(){const r=await api('projects');return r?r.projects:[];}

function renderProjectsPage(){
  const slot=remoteSlot('projects');
  const rows=(slot.data||[]).filter(p=>
    (!pf.q||`${p.name} ${p.address}`.toLowerCase().includes(pf.q.toLowerCase()))&&(pf.status==='All'||p.status===pf.status));
  const body=slot.data===null?LOADING_ROW(6):rows.map(p=>{
    const emps=p.assignedEmployees.map(e=>`<span class="badge badge-neutral" style="margin:1px">${esc(e.name)}</span>`).join('');
    return `<tr><td><strong>${esc(p.name)}</strong><div style="font-size:12px;color:#8896a6">${esc(p.description)}</div></td>
      <td>${esc(p.address)||'—'}</td>
      <td style="white-space:nowrap">${p.latitude.toFixed(5)}, ${p.longitude.toFixed(5)}<div style="font-size:12px;color:#8896a6">${p.radiusM} m · ${esc(p.timezone)}${p.startTime?` · starts ${p.startTime}`:''}</div></td>
      <td>${emps||'<span style="color:#8896a6">None</span>'}</td>
      <td><span class="badge ${p.status==='active'?'badge-income':'badge-neutral'}">${p.status}</span></td>
      <td style="white-space:nowrap"><button class="link-btn" onclick="projectForm('${p.id}')"><i class="fa-solid fa-pen-to-square"></i></button>
        <button class="link-btn" onclick="toggleProject('${p.id}')" title="${p.status==='active'?'Deactivate':'Activate'}"><i class="fa-solid fa-power-off"></i></button></td></tr>`;
  }).join('')||EMPTY_ROW(6,'No projects match the filters.');
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-diagram-project"></i> Projects</h2><button class="btn-primary" onclick="projectForm()"><i class="fa-solid fa-plus"></i> Add Project</button></div>
    <div class="page-title-bar" style="padding:12px 18px"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input placeholder="Search name or address…" value="${esc(pf.q)}" style="flex:1;min-width:180px;margin-top:0" oninput="pf.q=this.value;render()">
      <select style="width:auto;min-width:120px;margin-top:0" onchange="pf.status=this.value;render()">
        <option value="All">All statuses</option><option value="active" ${pf.status==='active'?'selected':''}>Active</option><option value="inactive" ${pf.status==='inactive'?'selected':''}>Inactive</option></select>
      <span style="font-size:12px;color:#8896a6;margin-left:auto">${rows.length} project(s)</span></div></div>
    <table class="list-table"><thead><tr><th>Project</th><th>Address</th><th>Location</th><th>Assigned Employees</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>${body}</tbody></table>`);
}

function projectBody(p){
  return {name:p.name,description:p.description,address:p.address,latitude:p.latitude,longitude:p.longitude,
    radiusM:p.radiusM,timezone:p.timezone,startTime:p.startTime,status:p.status};
}

async function toggleProject(id){
  const p=remoteSlot('projects').data.find(x=>x.id===id);
  const status=p.status==='active'?'inactive':'active';
  if(!await api('projects/'+id,'PUT',{...projectBody(p),status}))return;
  invalidateRemote('projects');render();toast(`Project ${status==='active'?'activated':'deactivated'}.`,'warn');
}

function projectForm(id){
  const p=id?remoteSlot('projects').data.find(x=>x.id===id):{name:'',description:'',address:'',latitude:DEFAULT_CENTER[0],longitude:DEFAULT_CENTER[1],radiusM:100,timezone:'Asia/Karachi',status:'active',assignedEmployees:[]};
  const assigned=new Set(p.assignedEmployees.map(e=>e.id));
  const emps=db.employees.map(e=>`<label class="chk"><input type="checkbox" value="${e.id}" ${assigned.has(e.id)?'checked':''}>${esc(e.name)}${e.designation?` <span style="color:#8896a6;margin-left:4px">· ${esc(e.designation)}</span>`:''}</label>`).join('')||'<p style="font-size:13px;color:#888">Add employees first.</p>';
  const tzOpts=timezones().map(z=>`<option ${z===p.timezone?'selected':''}>${z}</option>`).join('');
  showModal(`<div class="modal-header"><h3>${id?'Edit':'Add'} Project</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveProject(event,'${id||''}')">
      <div class="row-inline"><div><label>Project Name *</label><input id="pName" value="${esc(p.name)}" required maxlength="150"></div>
        <div><label>Status</label><select id="pStatus"><option value="active" ${p.status==='active'?'selected':''}>Active</option><option value="inactive" ${p.status==='inactive'?'selected':''}>Inactive</option></select></div></div>
      <label>Description</label><input id="pDesc" value="${esc(p.description)}">
      <label>Address</label>
      <div style="display:flex;gap:8px"><input id="pSearch" placeholder="Search an address…" value="${esc(p.address)}" style="margin-top:5px"><button type="button" class="btn-secondary" style="margin-top:5px;white-space:nowrap" onclick="searchAddress()"><i class="fa-solid fa-magnifying-glass"></i> Find</button></div>
      <div id="pMap" style="height:260px;border-radius:10px;margin-top:10px;border:1px solid var(--border)"></div>
      <p style="font-size:12px;color:#8896a6;margin:6px 0 0">Click the map or drag the pin to set the location.</p>
      <div class="row-inline">
        <div><label>Latitude *</label><input id="pLat" type="number" step="any" min="-90" max="90" value="${p.latitude}" required onchange="movePin()"></div>
        <div><label>Longitude *</label><input id="pLng" type="number" step="any" min="-180" max="180" value="${p.longitude}" required onchange="movePin()"></div>
        <div><label>Radius (m) *</label><input id="pRadius" type="number" min="10" max="5000" value="${p.radiusM}" required oninput="resizePin()"></div>
      </div>
      <div class="row-inline"><div><label>Timezone</label><select id="pTz">${tzOpts}</select></div>
        <div><label>Start Time</label><input id="pStart" type="time" value="${p.startTime||''}"></div></div>
      <label style="margin-top:14px">Assigned Employees</label>
      <div class="checklist">${emps}</div>
      <div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
    </form>`);
  initPickMap(p.latitude,p.longitude,p.radiusM);
}

function initPickMap(lat,lng,radius){
  pickMap=makeMap(document.getElementById('pMap'),lat,lng,16);
  pickMarker=L.marker([lat,lng],{draggable:true}).addTo(pickMap);
  pickCircle=L.circle([lat,lng],{radius,color:'#0b2e4f',fillOpacity:.12}).addTo(pickMap);
  pickMarker.on('dragend',()=>setPickCoords(pickMarker.getLatLng()));
  pickMap.on('click',e=>{pickMarker.setLatLng(e.latlng);setPickCoords(e.latlng);});
}

function setPickCoords({lat,lng}){
  document.getElementById('pLat').value=lat.toFixed(7);
  document.getElementById('pLng').value=lng.toFixed(7);
  pickCircle.setLatLng([lat,lng]);
}

function movePin(){
  const lat=Number(document.getElementById('pLat').value),lng=Number(document.getElementById('pLng').value);
  pickMarker.setLatLng([lat,lng]);pickCircle.setLatLng([lat,lng]);pickMap.panTo([lat,lng]);
}

function resizePin(){pickCircle.setRadius(Number(document.getElementById('pRadius').value)||0);}

async function searchAddress(){
  const q=document.getElementById('pSearch').value.trim();
  if(!q)return;
  const results=await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(q)).then(r=>r.json()).catch(()=>[]);
  if(!results[0]){toast('Address not found. Pick the location on the map.','warn');return;}
  const latlng={lat:Number(results[0].lat),lng:Number(results[0].lon)};
  pickMap.setView([latlng.lat,latlng.lng],16);
  pickMarker.setLatLng([latlng.lat,latlng.lng]);
  setPickCoords(latlng);
}

async function saveProject(e,id){
  e.preventDefault();
  const body={
    name:document.getElementById('pName').value.trim(),
    description:document.getElementById('pDesc').value.trim(),
    address:document.getElementById('pSearch').value.trim(),
    latitude:Number(document.getElementById('pLat').value),
    longitude:Number(document.getElementById('pLng').value),
    radiusM:Number(document.getElementById('pRadius').value),
    timezone:document.getElementById('pTz').value,
    startTime:document.getElementById('pStart').value,
    status:document.getElementById('pStatus').value,
  };
  const saved=await api(id?'projects/'+id:'projects',id?'PUT':'POST',body);
  if(!saved)return;
  const employeeIds=[...document.querySelectorAll('.modal-box .checklist input:checked')].map(c=>c.value);
  if(!await api(`projects/${saved.project.id}/assignments`,'PUT',{employeeIds}))return;
  invalidateRemote('projects');closeModal();render();toast('Project saved.');
}

/* ── ATTENDANCE MONITOR ── */
const localDate=()=>new Date().toLocaleDateString('en-CA');
let af={status:'all',projectId:'',date:localDate(),q:''};
let monitorMap=null;
const TRACKING_BADGE={current:['badge-income','Current'],stale:['badge-warn','Stale'],delayed:['badge-warn','Delayed'],unavailable:['badge-neutral','No data']};
const STATUS_BADGE={checked_in:['badge-income','Checked in'],checked_out:['badge-neutral','Checked out'],not_checked_in:['badge-expense','Not checked in'],on_leave:['badge-warn','On leave']};

async function fetchAttendance(){
  const [roster,projects]=await Promise.all([api('attendance/roster?'+new URLSearchParams({date:af.date})),api('projects?status=active')]);
  return {...(roster||{employees:[],counts:{}}),projects:projects?projects.projects:[]};
}

function renderAttendancePage(){
  const slot=remoteSlot('attendance');
  const data=slot.data||{employees:[],counts:{},projects:[]};
  const c=data.counts||{};
  const rows=(data.employees||[]).filter(r=>(af.status==='all'||r.status===af.status)
    &&(!af.projectId||r.session?.projectId===af.projectId)
    &&(!af.q||r.name.toLowerCase().includes(af.q.toLowerCase())));
  const projectOpts=(data.projects||[]).map(p=>`<option value="${p.id}" ${af.projectId===p.id?'selected':''}>${esc(p.name)}</option>`).join('');
  const card=(cls,label,value)=>`<div class="s-card ${cls}" style="padding:13px 16px"><div class="label">${label}</div><div class="value" style="font-size:17px;margin-top:4px">${value}</div></div>`;
  const body=slot.data===null?LOADING_ROW(8):rows.map(r=>{
    const s=r.session,badge=STATUS_BADGE[r.status];
    const leave=r.leave?`<div style="font-size:12px;color:#8896a6">${esc(r.leave.typeName)} · ${dateStr(r.leave.startDate)} – ${dateStr(r.leave.endDate)}</div>`:'';
    const track=TRACKING_BADGE[s?.lastLocation?.state]||TRACKING_BADGE.unavailable;
    return `<tr><td><button class="link-btn" onclick="openEmployeeReport('${r.id}','${af.date}')"><strong>${esc(r.name)}</strong></button><div style="font-size:12px;color:#8896a6">${esc(r.designation)}</div></td>
      <td><span class="badge ${badge[0]}">${badge[1]}</span>${leave}</td>
      <td>${s?esc(s.projectName):'—'}</td>
      <td style="white-space:nowrap">${s?fmtTime(s.checkIn.at,s.projectTimezone):'—'}${s&&s.lateMinutes!==null?`<div>${lateBadge(s.lateMinutes,s.isLate)}</div>`:''}</td>
      <td style="white-space:nowrap">${s?fmtDuration(s.durationSeconds):'—'}</td>
      <td style="white-space:nowrap">${r.status==='checked_in'?timeAgo(s.lastLocation.at):'—'}</td>
      <td>${r.status==='checked_in'?`<span class="badge ${track[0]}">${track[1]}</span>`:'—'}</td>
      <td>${s?`<button class="link-btn" onclick="trackSession('${s.id}')"><i class="fa-solid fa-route"></i> Trail</button>`:''}</td></tr>`;
  }).join('')||EMPTY_ROW(8,'No employees match the filters.');
  setTimeout(drawMonitorMap,0);
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-location-dot"></i> Live Attendance</h2>
      <button class="btn-secondary" onclick="invalidateRemote('attendance');render()"><i class="fa-solid fa-arrow-rotate-right"></i> Refresh</button></div>
    <div class="summary-cards" style="gap:10px;margin-bottom:14px">
      ${card('income','Checked in',c.checkedIn??0)}${card('expense','Not checked in',c.notCheckedIn??0)}${card('net','On leave',c.onLeave??0)}${card('','Checked out',c.checkedOut??0)}
    </div>
    <div class="page-title-bar" style="padding:12px 18px"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="date" value="${af.date}" style="width:auto;margin-top:0" onchange="setAttendanceFilter('date',this.value)">
      <select style="width:auto;min-width:150px;margin-top:0" onchange="af.status=this.value;render()">
        <option value="all" ${af.status==='all'?'selected':''}>All statuses</option>
        <option value="checked_in" ${af.status==='checked_in'?'selected':''}>Checked in</option>
        <option value="not_checked_in" ${af.status==='not_checked_in'?'selected':''}>Not checked in</option>
        <option value="on_leave" ${af.status==='on_leave'?'selected':''}>On leave</option>
        <option value="checked_out" ${af.status==='checked_out'?'selected':''}>Checked out</option></select>
      <select style="width:auto;min-width:160px;margin-top:0" onchange="af.projectId=this.value;render()"><option value="">All projects</option>${projectOpts}</select>
      <input id="attQ" placeholder="Search employee…" value="${esc(af.q)}" style="flex:1;min-width:160px;margin-top:0" oninput="af.q=this.value;render()">
      <span style="font-size:12px;color:#8896a6;margin-left:auto">Refreshes every minute</span></div></div>
    <div id="monMap" style="height:300px;border-radius:12px;margin:14px 0 16px;border:1px solid var(--border)"></div>
    <table class="list-table"><thead><tr><th>Employee</th><th>Status</th><th>Project</th><th>Check-in</th><th>On site</th><th>Last update</th><th>Tracking</th><th></th></tr></thead>
      <tbody>${body}</tbody></table>`);
}

function setAttendanceFilter(key,value){af[key]=value;invalidateRemote('attendance');render();}

function drawMonitorMap(){
  const el=document.getElementById('monMap');
  if(!el)return;
  const slot=remoteSlot('attendance');
  const sessions=(slot.data?.employees||[]).filter(r=>r.status==='checked_in').map(r=>r.session);
  monitorMap=makeMap(el,DEFAULT_CENTER[0],DEFAULT_CENTER[1],12);
  const bounds=[];
  (slot.data?.projects||[]).forEach(p=>{
    L.circle([p.latitude,p.longitude],{radius:p.radiusM,color:'#0b2e4f',fillOpacity:.08}).bindTooltip(esc(p.name)).addTo(monitorMap);
    bounds.push([p.latitude,p.longitude]);
  });
  sessions.forEach(s=>{
    const loc=s.lastLocation;
    if(loc.latitude===null)return;
    L.circleMarker([loc.latitude,loc.longitude],{radius:8,color:loc.inside?'#1e8449':'#c0392b',fillOpacity:.9})
      .bindPopup(`<strong>${esc(s.employeeName)}</strong><br>${esc(s.projectName)}<br>Updated ${timeAgo(loc.at)}<br>${loc.distanceM??'—'} m from centre`)
      .addTo(monitorMap);
    bounds.push([loc.latitude,loc.longitude]);
  });
  if(bounds.length)monitorMap.fitBounds(bounds,{padding:[30,30],maxZoom:16});
}

async function trackSession(id){
  showModal(`<div class="modal-header"><h3>Attendance Trail</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div><p>Loading…</p>`);
  const t=await api(`attendance/sessions/${id}/track`);
  if(!t){closeModal();return;}
  const s=t.session,tz=s.projectTimezone,c=s.projectLocation;
  const gaps=t.gaps.length?t.gaps.map(g=>`<li>${fmtTime(g.from,tz)} → ${fmtTime(g.to,tz)} (${fmtDuration(g.seconds)})</li>`).join(''):'<li>None</li>';
  const outside=t.outOfBoundary.length?t.outOfBoundary.map(p=>`<li>${fmtTime(p.at,tz)} · ${p.distanceM} m from centre</li>`).join(''):'<li>None</li>';
  showModal(`<div class="modal-header"><h3>${esc(s.employeeName)} · ${esc(s.projectName)}</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <p style="font-size:13px;color:#5d7a96">Check-in ${fmtTime(s.checkIn.at,tz)}${s.checkOut?` · Check-out ${fmtTime(s.checkOut.at,tz)}`:''} · ${fmtDuration(s.durationSeconds)} · Exceptions: ${esc(s.exceptions.join(', ')||'none')}</p>
    <div id="trackMap" style="height:300px;border-radius:10px;border:1px solid var(--border)"></div>
    <div class="row-inline" style="margin-top:14px"><div><strong>Tracking interruptions</strong><ul style="font-size:13px">${gaps}</ul></div>
      <div><strong>Out-of-boundary events</strong><ul style="font-size:13px">${outside}</ul></div></div>`);
  const map=makeMap(document.getElementById('trackMap'),c.latitude,c.longitude,16);
  L.circle([c.latitude,c.longitude],{radius:c.radiusM,color:'#0b2e4f',fillOpacity:.08}).addTo(map);
  const path=t.points.map(p=>[p.latitude,p.longitude]);
  if(path.length){
    L.polyline(path,{color:'#e0952e',weight:4}).addTo(map);
    map.fitBounds(path,{padding:[30,30],maxZoom:17});
  }
  t.outOfBoundary.forEach(p=>L.circleMarker([p.latitude,p.longitude],{radius:6,color:'#c0392b'}).addTo(map));
}

/* ── ATTENDANCE REPORT ── */
let arf={month:new Date().toISOString().slice(0,7),employeeId:''};

async function fetchAttendanceReport(){
  const params=new URLSearchParams({month:arf.month});
  if(arf.employeeId){
    params.set('employeeId',arf.employeeId);
    return (await api('attendance/report?'+params))||{};
  }
  return (await api('attendance/report/summary?'+params))||{};
}

function setAttReport(key,value){arf[key]=value;invalidateRemote('attreport');render();}
function openEmployeeReport(employeeId,date){
  arf={month:date.slice(0,7),employeeId};
  invalidateRemote('attreport');
  navigate('attreport');
}

function renderAttendanceReportPage(){
  const d=remoteSlot('attreport').data;
  const emps=db.employees.map(e=>`<option value="${e.id}" ${arf.employeeId===e.id?'selected':''}>${esc(e.name)}</option>`).join('');
  const body=d===null?'<p style="color:#8896a6">Loading…</p>':arf.employeeId?employeeReportHtml(d):summaryReportHtml(d);
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-clock"></i> Attendance Report</h2>
      <button class="btn-secondary" onclick="exportAttendanceReport()"><i class="fa-solid fa-file-csv"></i> Export CSV</button></div>
    <div class="page-title-bar" style="padding:12px 18px"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="month" value="${arf.month}" style="width:auto;margin-top:0" onchange="setAttReport('month',this.value)">
      <select style="width:auto;min-width:240px;margin-top:0" onchange="setAttReport('employeeId',this.value)">
        <option value="">All employees (monthly summary)</option>${emps}</select>
      <span style="font-size:12px;color:#8896a6;margin-left:auto">On-site time = check-in to check-out, per project day</span></div></div>
    ${body}`);
}

function summaryReportHtml(d){
  const rows=d.employees||[];
  const total=rows.reduce((t,r)=>({days:t.days+r.daysPresent,sessions:t.sessions+r.sessions,seconds:t.seconds+r.totalSeconds,late:t.late+r.daysLate}),{days:0,sessions:0,seconds:0,late:0});
  return `<table class="list-table"><thead><tr><th>Employee</th><th>Designation</th><th>Days present</th><th>Sessions</th><th>Total on site</th><th>Days late</th><th>Days with exceptions</th></tr></thead>
    <tbody>${rows.map(r=>`<tr><td><button class="link-btn" onclick="setAttReport('employeeId','${r.employeeId}')"><strong>${esc(r.name)}</strong></button></td>
      <td>${esc(r.designation)||'—'}</td><td>${r.daysPresent}</td><td>${r.sessions}</td><td style="white-space:nowrap">${fmtDuration(r.totalSeconds)}</td><td>${r.daysLate}</td><td>${r.daysWithExceptions}</td></tr>`).join('')||EMPTY_ROW(7,'No employees.')}</tbody>
    ${rows.length?`<tfoot><tr style="background:#f7f9fc"><td colspan="2" style="font-weight:800;padding:10px 14px">TOTAL</td><td style="font-weight:800">${total.days}</td><td style="font-weight:800">${total.sessions}</td><td style="font-weight:800;white-space:nowrap">${fmtDuration(total.seconds)}</td><td style="font-weight:800">${total.late}</td><td></td></tr></tfoot>`:''}</table>`;
}

function employeeReportHtml(d){
  if(!d.employee)return '<p style="color:#8896a6">Could not load this report.</p>';
  const s=d.summary,tz=d.timezone;
  const card=(label,value)=>`<div class="s-card net" style="padding:13px 16px"><div class="label">${label}</div><div class="value" style="font-size:17px;margin-top:4px">${value}</div></div>`;
  const dayRows=d.days.map(x=>`<tr><td style="white-space:nowrap">${dateStr(x.date)}</td><td>${fmtTime(x.firstCheckIn,tz)}</td><td>${lateBadge(x.lateMinutes,x.isLate)}</td><td>${fmtTime(x.lastCheckOut,tz)}</td>
    <td>${x.sessions}</td><td style="white-space:nowrap">${fmtDuration(x.totalSeconds)}</td>
    <td>${x.openSessions?'<span class="badge badge-warn" style="margin:1px">No check-out</span>':''}${x.exceptions.map(e=>`<span class="badge badge-warn" style="margin:1px">${esc(e)}</span>`).join('')||''}${!x.openSessions&&!x.exceptions.length?'—':''}</td></tr>`).join('');
  return `<h3 style="margin:16px 0 10px;color:var(--primary)">${esc(d.employee.name)}${d.employee.designation?` · ${esc(d.employee.designation)}`:''} · Shift ${d.employee.shiftHours} h — ${d.month}</h3>
    <div class="summary-cards" style="gap:10px;margin-bottom:14px">
      ${card('Days present',s.daysPresent)}${card('Sessions',s.sessions)}${card('Total on site',fmtDuration(s.totalSeconds))}${card('Total hours',s.totalHours.toFixed(2))}${card('Days late',s.daysLate)}${card('Days with exceptions',s.daysWithExceptions)}
    </div>
    <table class="list-table"><thead><tr><th>Date</th><th>First check-in</th><th>Late</th><th>Last check-out</th><th>Sessions</th><th>On site</th><th>Exceptions</th></tr></thead>
    <tbody>${dayRows||EMPTY_ROW(7,'No attendance recorded this month.')}</tbody></table>`;
}

function exportAttendanceReport(){
  const d=remoteSlot('attreport').data;
  if(!d?.days&&!d?.employees)return;
  let rows,name;
  if(arf.employeeId){
    rows=[['Date','First check-in','Late (min)','Last check-out','Sessions','On site (h)','Exceptions'],
      ...d.days.map(x=>[x.date,x.firstCheckIn||'',x.lateMinutes??'',x.lastCheckOut||'',x.sessions,(x.totalSeconds/3600).toFixed(2),x.exceptions.join(' ')])];
    name=`attendance-${d.employee.name}-${arf.month}`;
  }else{
    rows=[['Employee','Designation','Days present','Sessions','On site (h)','Days late','Days with exceptions'],
      ...d.employees.map(r=>[r.name,r.designation,r.daysPresent,r.sessions,(r.totalSeconds/3600).toFixed(2),r.daysLate,r.daysWithExceptions])];
    name=`attendance-summary-${arf.month}`;
  }
  const csv=rows.map(r=>r.map(c=>`"${String(c??'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));
  a.download=name.replace(/[^\w.-]+/g,'_')+'.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

/* ── LEAVE REQUESTS ── */
let lf={status:'pending'};

async function fetchLeaves(){
  const r=await api('leaves?perPage=100'+(lf.status!=='All'?'&status='+lf.status:''));
  return r?r.items:[];
}

const LEAVE_BADGE={pending:'badge-warn',approved:'badge-income',rejected:'badge-expense',cancelled:'badge-neutral'};

function renderLeavesPage(){
  const slot=remoteSlot('leaves');
  const body=slot.data===null?LOADING_ROW(7):slot.data.map(l=>`<tr>
      <td><strong>${esc(l.employeeName)}</strong></td><td>${esc(l.leaveTypeName)}</td>
      <td style="white-space:nowrap">${dateStr(l.startDate)} – ${dateStr(l.endDate)}</td><td>${l.days}</td>
      <td style="max-width:260px;font-size:13px">${esc(l.reason)}${l.adminRemarks?`<div style="color:#8896a6;font-size:12px">Remarks: ${esc(l.adminRemarks)}</div>`:''}</td>
      <td><span class="badge ${LEAVE_BADGE[l.status]}">${l.status}</span></td>
      <td>${l.status==='pending'?`<button class="btn-secondary" style="padding:6px 12px" onclick="reviewLeave('${l.id}')">Review</button>`:`<span style="font-size:12px;color:#8896a6">${fmtTime(l.reviewedAt)}</span>`}</td></tr>`).join('')
    ||EMPTY_ROW(7,'No leave requests match this filter.');
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-calendar-check"></i> Leave Requests</h2></div>
    <div class="page-title-bar" style="padding:12px 18px"><div style="display:flex;gap:8px;align-items:center">
      <select style="width:auto;min-width:150px;margin-top:0" onchange="lf.status=this.value;invalidateRemote('leaves');render()">
        ${['pending','approved','rejected','cancelled','All'].map(s=>`<option value="${s}" ${lf.status===s?'selected':''}>${s==='All'?'All statuses':s[0].toUpperCase()+s.slice(1)}</option>`).join('')}</select></div></div>
    <table class="list-table"><thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
      <tbody>${body}</tbody></table>`);
}

function reviewLeave(id){
  const l=remoteSlot('leaves').data.find(x=>x.id===id);
  showModal(`<div class="modal-header"><h3>Review Leave</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <p><strong>${esc(l.employeeName)}</strong> · ${esc(l.leaveTypeName)} · ${dateStr(l.startDate)} – ${dateStr(l.endDate)} (${l.days} day${l.days===1?'':'s'})</p>
    <p style="background:#f5f8fb;padding:10px 12px;border-radius:9px;font-size:14px">${esc(l.reason)}</p>
    <label>Remarks (optional)</label><textarea id="lRemarks" rows="3" maxlength="255"></textarea>
    <div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
      <button type="button" class="btn-secondary" style="color:#c0392b" onclick="submitLeaveReview('${id}','rejected')">Reject</button>
      <button type="button" class="btn-primary" onclick="submitLeaveReview('${id}','approved')">Approve</button></div>`);
}

async function submitLeaveReview(id,status){
  const remarks=document.getElementById('lRemarks').value.trim();
  if(!await api(`leaves/${id}/review`,'POST',{status,remarks}))return;
  invalidateRemote('leaves');closeModal();render();toast(`Leave ${status}.`);
}

/* ── ANNOUNCEMENTS ── */
async function fetchAnnouncements(){const r=await api('announcements?perPage=100');return r?r.items:[];}

function renderAnnouncementsPage(){
  const slot=remoteSlot('announcements');
  const body=slot.data===null?LOADING_ROW(6):slot.data.map(a=>`<tr>
      <td><strong>${esc(a.title)}</strong><div style="font-size:12px;color:#8896a6">${esc((a.content||'').replace(/<[^>]*>/g,'').slice(0,90))}</div></td>
      <td>${a.audience==='all'?'All employees':`${a.employeeIds.length} selected`}</td>
      <td><span class="badge ${a.status==='published'?'badge-income':'badge-neutral'}">${a.status}</span></td>
      <td style="white-space:nowrap">${a.publishedAt?fmtTime(a.publishedAt):'—'}</td>
      <td style="font-size:13px">${esc(a.createdBy)}</td>
      <td style="white-space:nowrap">${a.status==='draft'?`<button class="link-btn" onclick="announcementForm('${a.id}')"><i class="fa-solid fa-pen-to-square"></i></button>
        <button class="link-btn" onclick="publishAnnouncement('${a.id}')" title="Publish and notify"><i class="fa-solid fa-paper-plane"></i> Publish</button>`:'<span style="font-size:12px;color:#8896a6">Published</span>'}</td></tr>`).join('')
    ||EMPTY_ROW(6,'No announcements yet.');
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-bullhorn"></i> Announcements</h2><button class="btn-primary" onclick="announcementForm()"><i class="fa-solid fa-plus"></i> New Announcement</button></div>
    <table class="list-table"><thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Published</th><th>Created by</th><th></th></tr></thead>
      <tbody>${body}</tbody></table>`);
}

function announcementForm(id){
  const a=id?remoteSlot('announcements').data.find(x=>x.id===id):{title:'',content:'',audience:'all',employeeIds:[]};
  const selected=new Set(a.employeeIds);
  const emps=db.employees.map(e=>`<label class="chk"><input type="checkbox" value="${e.id}" ${selected.has(e.id)?'checked':''}>${esc(e.name)}</label>`).join('');
  showModal(`<div class="modal-header"><h3>${id?'Edit':'New'} Announcement</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveAnnouncement(event,'${id||''}')">
      <label>Title *</label><input id="aTitle" value="${esc(a.title)}" required maxlength="200">
      <label>Content</label><textarea id="aContent" rows="5">${esc(a.content)}</textarea>
      <label>Audience</label>
      <select id="aAudience" onchange="document.getElementById('aEmps').style.display=this.value==='selected'?'block':'none'">
        <option value="all" ${a.audience==='all'?'selected':''}>All employees</option><option value="selected" ${a.audience==='selected'?'selected':''}>Selected employees</option></select>
      <div id="aEmps" class="checklist" style="display:${a.audience==='selected'?'block':'none'}">${emps}</div>
      <div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Draft</button></div>
    </form>`);
}

async function saveAnnouncement(e,id){
  e.preventDefault();
  const body={
    title:document.getElementById('aTitle').value.trim(),
    content:document.getElementById('aContent').value.trim(),
    audience:document.getElementById('aAudience').value,
    employeeIds:[...document.querySelectorAll('#aEmps input:checked')].map(c=>c.value),
  };
  if(!await api(id?'announcements/'+id:'announcements',id?'PUT':'POST',body))return;
  invalidateRemote('announcements');closeModal();render();toast('Announcement saved as draft.');
}

async function publishAnnouncement(id){
  const a=remoteSlot('announcements').data.find(x=>x.id===id);
  const count=a.audience==='all'?db.employees.length:a.employeeIds.length;
  if(!confirm(`Publish "${a.title}" and notify ${count} employee(s)? This cannot be undone.`))return;
  if(!await api(`announcements/${id}/publish`,'POST',{}))return;
  invalidateRemote('announcements');render();toast('Announcement published.');
}

setInterval(()=>{if(session&&route==='attendance'){invalidateRemote('attendance');ensureRemote('attendance');}},60000);
