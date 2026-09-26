/* ═══════════════════════════════════════════════════
   BB BUILDERS ERP
   Backend: CodeIgniter 4 REST API + MySQL
   Export: JSON backup + Excel + PDF
═══════════════════════════════════════════════════ */

/* ── API ── */
// Tries pretty URLs first, then index.php/api/… for hosts without mod_rewrite
const API_BASES=['api/','index.php/api/'];
let API_BASE='api/'; // updated to whichever base works (for <img>/<a> URLs)
async function api(path,method='GET',body,quiet=false){
  const opt={method};
  if(body!==undefined){opt.headers={'Content-Type':'application/json'};opt.body=JSON.stringify(body);}
  let res=null;
  for(const base of API_BASES){
    try{res=await fetch(base+path,opt);}catch(e){res=null;continue;}
    if(res.status===404)continue;
    API_BASE=base;
    break;
  }
  if(!res||res.status===404){if(!quiet)toast('Cannot reach server API. Check .htaccess/mod_rewrite.','error');return null;}
  const data=await res.json().catch(()=>({}));
  if(res.status===401){if(!quiet){session=null;route='login';render();}return null;}
  if(!res.ok){if(!quiet)toast(data.error||'Request failed.','error');return null;}
  return data;
}
async function loadState(){const s=await api('state');if(s)db=s;}
function readFileData(file){return new Promise((res,rej)=>{const r=new FileReader();r.onload=()=>res(r.result);r.onerror=rej;r.readAsDataURL(file);});}

/* ── LOCAL STATE ── */
function defaultSettings(){
  return{companyName:'BB Builders',tagline:'Construction & Development',address:'',phone:'',email:'',logo:'',
    primary:'#0b2e4f',secondary:'#e0952e',adminUser:'BBAccounts',currency:'Rs',taxRate:0,
    invoiceFooter:'Thank you for your business!'};
}
let db={settings:defaultSettings(),employees:[],heads:[],vouchers:[],invoices:[],salary:[]};

/* ── SESSION ── */
let session=null;
let route='login';
let printReturnRoute=null;
let printContent='',printFilename='';
let tempInvoiceItems=[];
let currentReportRows=[];
let reportMeta={from:'',to:''};

/* ── HELPERS ── */
function money(n){return (db.settings.currency||'Rs')+' '+Number(n||0).toLocaleString('en-PK');}
function dateStr(d){if(!d||d==='-')return '-';const dt=new Date(d);return isNaN(dt)?d:dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});}
function initials(n){return(n||'BB').split(' ').filter(Boolean).map(w=>w[0]).join('').substring(0,2).toUpperCase();}
function currentEmployee(){return db.employees.find(e=>e.id===session?.id);}
function applyTheme(){
  document.documentElement.style.setProperty('--primary',db.settings.primary||'#0b2e4f');
  document.documentElement.style.setProperty('--secondary',db.settings.secondary||'#e0952e');
}
function recordCount(){return db.heads.length+db.vouchers.length+db.invoices.length+db.salary.length+db.employees.length;}

/* ── TOAST ── */
function toast(msg,type='success'){
  const wrap=document.getElementById('toastWrap');
  if(!wrap)return;
  const el=document.createElement('div');
  el.className='toast-item';
  const bg=type==='success'?'#1e8449':type==='warn'?'#b7791f':'#c0392b';
  el.style.cssText=`background:${bg};color:#fff;padding:12px 18px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 15px rgba(0,0,0,.25);pointer-events:auto;max-width:320px;display:flex;align-items:center;gap:9px;`;
  const icon=document.createElement('i');
  icon.className='fa-solid '+(type==='success'?'fa-circle-check':type==='warn'?'fa-triangle-exclamation':'fa-circle-xmark');
  el.appendChild(icon);
  el.appendChild(document.createTextNode(msg));
  wrap.appendChild(el);
  setTimeout(()=>el.remove(),3500);
}

/* ── MODAL ── */
function showModal(html){document.getElementById('modalBox').innerHTML=html;document.getElementById('modalOverlay').classList.remove('hidden');}
function closeModal(){document.getElementById('modalOverlay').classList.add('hidden');document.getElementById('modalBox').innerHTML='';}
document.getElementById('modalOverlay').addEventListener('click',function(e){if(e.target===this)closeModal();});

/* ── DOC HEADER ── */
function docHeader(sub=''){
  const s=db.settings;
  const logo=s.logo?`<img src="${s.logo}" style="width:72px;height:72px;object-fit:contain;border-radius:8px;">`
    :`<div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,${s.primary},${s.secondary});color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:24px;">${initials(s.companyName)}</div>`;
  return `<div class="doc-header">${logo}<div style="flex:1"><h1>${s.companyName}</h1><p>${s.tagline||''}</p><p>${s.address||''}${s.phone?' | '+s.phone:''}${s.email?' | '+s.email:''}</p></div>${sub?`<div style="text-align:right;font-size:13px;color:#888">${sub}</div>`:''}</div>`;
}

function sideLogo(){
  const s=db.settings;
  const logo=s.logo?`<img src="${s.logo}" style="width:56px;height:56px;object-fit:contain;border-radius:50%;background:#fff;padding:3px;">`:`<div class="logo-circle">${initials(s.companyName)}</div>`;
  return `<div class="sidebar-logo">${logo}<h3>${s.companyName}</h3><p>${s.tagline||''}</p></div>`;
}

/* ═══════════════════════════════════════════════════
   AUTH
═══════════════════════════════════════════════════ */
function renderLogin(){
  const s=db.settings;
  const logo=s.logo?`<img src="${s.logo}" style="width:70px;border-radius:10px;">`
    :`<div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,${s.primary},${s.secondary});color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;margin:0 auto;font-size:24px;">${initials(s.companyName)}</div>`;
  return `<div class="login-wrap"><div class="login-card">
    ${logo}<h2>${s.companyName}</h2><p class="sub">${s.tagline||'ERP System'}</p>
    <form onsubmit="doLogin(event)">
      <label>Username</label><input id="lUser" placeholder="Enter username" autocomplete="username" required>
      <label style="margin-top:14px">Password</label><input id="lPass" type="password" placeholder="Enter password" required>
      <button class="btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
      <p id="loginErr" style="color:#c0392b;font-size:13px;margin-top:12px;min-height:18px"></p>
    </form>
    <p style="font-size:11px;color:#bbb;margin-top:20px">BB Builders ERP • Powered by MySQL</p>
  </div></div>`;
}

async function doLogin(e){
  e.preventDefault();
  const u=document.getElementById('lUser').value.trim();
  const p=document.getElementById('lPass').value;
  const r=await api('login','POST',{username:u,password:p},true);
  if(r&&r.user){session=r.user;await loadState();route=savedRoute(session.role);sessionStorage.setItem('bb_route',route);render();toast(`Welcome, ${session.name}!`);}
  else{document.getElementById('loginErr').textContent='Invalid username or password.';}
}

async function logout(){
  if(!confirm('Logout?'))return;
  await api('logout','POST',{},true);
  session=null;route='login';sessionStorage.removeItem('bb_route');render();
}

/* ═══════════════════════════════════════════════════
   SHELL
═══════════════════════════════════════════════════ */
function shell(content){
  const navAdmin=[
    {r:'dashboard',icon:'<i class="fa-solid fa-gauge-high"></i>',label:'Dashboard',sec:'MAIN'},
    {r:'heads',icon:'<i class="fa-solid fa-folder-tree"></i>',label:'Account Heads',sec:'ACCOUNTING'},
    {r:'vouchers',icon:'<i class="fa-solid fa-receipt"></i>',label:'Vouchers'},
    {r:'invoices',icon:'<i class="fa-solid fa-file-invoice"></i>',label:'Invoices'},
    {r:'reports',icon:'<i class="fa-solid fa-chart-line"></i>',label:'Reports'},
    {r:'employees',icon:'<i class="fa-solid fa-users"></i>',label:'Employees',sec:'HR'},
    {r:'salary',icon:'<i class="fa-solid fa-money-bill-wave"></i>',label:'Salary Slips'},
    {r:'settings',icon:'<i class="fa-solid fa-gear"></i>',label:'Settings',sec:'SYSTEM'},
  ];
  const navEmp=[{r:'add',icon:'<i class="fa-solid fa-circle-plus"></i>',label:'Add Entry',sec:'ENTRIES'},{r:'mine',icon:'<i class="fa-solid fa-clipboard-list"></i>',label:'My Entries'}];
  const nav=session.role==='admin'?navAdmin:navEmp;
  let lastSec='';
  const navHtml=nav.map(item=>{
    let sec='';
    if(item.sec&&item.sec!==lastSec){sec=`<div class="nav-section">${item.sec}</div>`;lastSec=item.sec;}
    return `${sec}<a class="${route===item.r?'active':''}" onclick="navigate('${item.r}')"><span class="nav-icon">${item.icon}</span>${item.label}</a>`;
  }).join('');
  return `<div class="app-shell">
    <aside class="sidebar">
      ${sideLogo()}
      <nav>${navHtml}</nav>
      <div class="storage-bar">
        <div class="storage-dot" style="background:#1e8449"></div>
        <span style="color:#5d7a96;font-size:11px" id="storageInfo">${recordCount()} records</span>
      </div>
      <button class="btn-logout" onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i> Logout — ${session.name}</button>
    </aside>
    <main class="content">${content}</main>
  </div>`;
}

function navigate(r){route=r;sessionStorage.setItem('bb_route',r);render();}
function savedRoute(role){
  const allowed=role==='admin'?['dashboard','heads','employees','vouchers','invoices','salary','reports','settings']:['add','mine'];
  const r=sessionStorage.getItem('bb_route');
  return allowed.includes(r)?r:(role==='admin'?'dashboard':'add');
}

/* ═══════════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════════ */
function renderDashboard(){
  const now=new Date();
  const ym=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  const tm=db.vouchers.filter(v=>v.date?.slice(0,7)===ym);
  const inc=tm.filter(v=>v.type==='Receipt').reduce((s,v)=>s+v.amount,0);
  const exp=tm.filter(v=>v.type==='Payment').reduce((s,v)=>s+v.amount,0);
  const aInc=db.vouchers.filter(v=>v.type==='Receipt').reduce((s,v)=>s+v.amount,0);
  const aExp=db.vouchers.filter(v=>v.type==='Payment').reduce((s,v)=>s+v.amount,0);
  const recent=[...db.vouchers].sort((a,b)=>(b.date||'').localeCompare(a.date||'')).slice(0,10);
  const mn=now.toLocaleString('en',{month:'long'});
  return shell(`
    <div class="page-header">
      <h2><i class="fa-solid fa-gauge-high"></i> Dashboard</h2>
      <span style="font-size:13px;color:#888">${now.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}</span>
    </div>
    ${db.heads.length===0?`<div style="background:#fff8e6;border:1px solid #ffd97d;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:14px;color:#9a6800">
      <i class="fa-solid fa-triangle-exclamation"></i> No account heads yet — <a onclick="navigate('heads')" style="cursor:pointer;font-weight:700;color:var(--primary)">Add your first head</a> to start recording transactions.
    </div>`:''}
    <div class="summary-cards">
      <div class="s-card income"><div class="label">${mn} Income</div><div class="value">${money(inc)}</div></div>
      <div class="s-card expense"><div class="label">${mn} Expense</div><div class="value">${money(exp)}</div></div>
      <div class="s-card net"><div class="label">${mn} Net</div><div class="value">${money(inc-exp)}</div></div>
      <div class="s-card neutral"><div class="label">Overall Balance</div><div class="value">${money(aInc-aExp)}</div></div>
    </div>
    <div class="quick-actions">
      <button class="btn-primary" onclick="voucherForm()"><i class="fa-solid fa-receipt"></i> New Voucher</button>
      <button class="btn-secondary" onclick="navigate('invoices');setTimeout(invoiceForm,50)"><i class="fa-solid fa-file-invoice"></i> New Invoice</button>
      <button class="btn-secondary" onclick="navigate('salary');setTimeout(salaryForm,50)"><i class="fa-solid fa-money-bill-wave"></i> New Salary Slip</button>
      <button class="btn-secondary" onclick="navigate('reports')"><i class="fa-solid fa-chart-line"></i> Reports</button>
    </div>
    <div class="page-title-bar">
      <strong>Recent Transactions</strong>
      <button class="btn-secondary" style="font-size:13px;padding:7px 14px" onclick="navigate('vouchers')">View All <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <table class="list-table">
      <thead><tr><th>Date</th><th>No.</th><th>Head</th><th>Type</th><th>Party</th><th>Amount</th><th>By</th><th></th></tr></thead>
      <tbody>${recent.map(v=>{
        const h=db.heads.find(x=>x.id===v.headId)||{name:'—'};
        const badge=v.type==='Payment'?'<span class="badge badge-expense">Payment</span>':'<span class="badge badge-income">Receipt</span>';
        return `<tr><td>${dateStr(v.date)}</td><td><strong>${v.no}</strong></td><td>${h.name}</td><td>${badge}</td><td>${v.party||'—'}</td><td><strong>${money(v.amount)}</strong></td><td style="color:#888;font-size:12px">${v.createdBy}</td><td><button class="link-btn" onclick="openVoucherPrint('${v.id}')">View</button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="8">No transactions yet.</td></tr>'}</tbody>
    </table>`);
}

/* ═══════════════════════════════════════════════════
   ACCOUNT HEADS
═══════════════════════════════════════════════════ */
function renderHeadsPage(){
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-folder-tree"></i> Account Heads</h2><button class="btn-primary" onclick="headForm()"><i class="fa-solid fa-plus"></i> Add Head</button></div>
    <table class="list-table">
      <thead><tr><th>Head Name</th><th>Type</th><th>Group</th><th>Total Activity</th><th>Actions</th></tr></thead>
      <tbody>${db.heads.map(h=>{
        const vol=db.vouchers.filter(v=>v.headId===h.id).reduce((s,v)=>s+v.amount,0);
        const subs=(db.subHeads||[]).filter(s=>s.headId===h.id);
        const badge=h.type==='Expense'?'<span class="badge badge-expense">Expense</span>':'<span class="badge badge-income">Income</span>';
        return `<tr><td><strong>${h.name}</strong>${subs.length?`<div style="margin-top:4px">${subs.map(s=>`<span class="badge badge-neutral" style="margin:1px;font-size:10px">${s.name}</span>`).join('')}</div>`:''}</td><td>${badge}</td><td>${h.group?`<span class="badge badge-neutral">${h.group}</span>`:'—'}</td><td><strong>${money(vol)}</strong></td>
        <td><button class="link-btn" onclick="headForm('${h.id}')"><i class="fa-solid fa-pen-to-square"></i> Edit</button> <button class="link-btn danger" onclick="deleteHead('${h.id}')"><i class="fa-solid fa-trash"></i> Delete</button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="5">No heads yet. Add your first account head above.</td></tr>'}</tbody>
    </table>`);
}

function subHeadRow(id,name){
  return `<div style="display:flex;gap:8px;margin-top:6px;align-items:center"><input class="subName" data-id="${id||''}" value="${name||''}" placeholder="Sub-head name" style="margin-top:0"><button type="button" class="link-btn danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button></div>`;
}
function addSubHeadRow(){
  document.getElementById('subList').insertAdjacentHTML('beforeend',subHeadRow('',''));
  document.querySelector('#subList .subName:last-of-type')?.focus();
}

function headForm(id){
  const h=id?db.heads.find(x=>x.id===id):{name:'',type:'Expense',group:''};
  const subs=id?(db.subHeads||[]).filter(s=>s.headId===id):[];
  showModal(`<div class="modal-header"><h3>${id?'Edit':'Add'} Account Head</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <p style="font-size:13px;color:#888;margin-bottom:4px">e.g. "Project Alpha", "Office Rent", "Client Payments"</p>
    <form onsubmit="saveHead(event,'${id||''}')">
      <label>Head Name *</label><input id="fName" value="${h.name||''}" required placeholder="e.g. Project Alpha">
      <label>Type *</label>
      <select id="fType">
        <option value="Expense" ${h.type==='Expense'?'selected':''}>Expense → creates Payment Voucher</option>
        <option value="Income"  ${h.type==='Income'?'selected':''}>Income → creates Receipt Voucher</option>
      </select>
      <label>Group / Category</label>
      <input id="fGroup" value="${h.group||''}" placeholder="e.g. Projects, Admin, HR">
      <label>Sub-Heads <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional — e.g. Kitchen, Stationery)</span></label>
      <div id="subList">${subs.map(s=>subHeadRow(s.id,s.name)).join('')}</div>
      <button type="button" class="btn-secondary" style="margin-top:8px;padding:7px 14px;font-size:12.5px" onclick="addSubHeadRow()"><i class="fa-solid fa-plus"></i> Add Sub-Head</button>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Head</button>
      </div>
    </form>`);
}

async function saveHead(e,id){
  e.preventDefault();
  const data={name:document.getElementById('fName').value.trim(),type:document.getElementById('fType').value,group:document.getElementById('fGroup').value.trim(),
    subHeads:[...document.querySelectorAll('#subList .subName')].map(i=>({id:i.dataset.id||undefined,name:i.value.trim()})).filter(s=>s.name)};
  if(!data.name){toast('Head name required.','error');return;}
  if(!await api('heads'+(id?'/'+id:''),id?'PUT':'POST',data))return;
  await loadState();closeModal();render();toast('Head saved.');
}

async function deleteHead(id){
  const h=db.heads.find(x=>x.id===id);
  if(!confirm(`Delete "${h.name}"?`))return;
  if(!await api('heads/'+id,'DELETE'))return;
  await loadState();render();toast('Head deleted.','warn');
}

/* ═══════════════════════════════════════════════════
   EMPLOYEES
═══════════════════════════════════════════════════ */
function renderEmployeesPage(){
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-users"></i> Employees & Access</h2><button class="btn-primary" onclick="employeeForm()"><i class="fa-solid fa-plus"></i> Add Employee</button></div>
    <table class="list-table">
      <thead><tr><th>Name</th><th>Phone</th><th>Designation</th><th>Login</th><th>Allowed Heads</th><th>Actions</th></tr></thead>
      <tbody>${db.employees.map(emp=>{
        const heads=(emp.assignedHeads||[]).map(id=>{const h=db.heads.find(x=>x.id===id);return h?`<span class="badge badge-neutral" style="margin:1px">${h.name}</span>`:null;}).filter(Boolean).join('');
        const lb=emp.loginEnabled?`<span class="badge badge-income"><i class="fa-solid fa-check"></i> ${emp.username}</span>`:`<span class="badge badge-neutral">Disabled</span>`;
        return `<tr><td><strong>${emp.name}</strong></td><td>${emp.phone||'—'}</td><td>${emp.designation||'—'}</td><td>${lb}</td><td>${heads||'—'}</td>
        <td><button class="link-btn" onclick="employeeForm('${emp.id}')"><i class="fa-solid fa-pen-to-square"></i></button> <button class="link-btn danger" onclick="deleteEmployee('${emp.id}')"><i class="fa-solid fa-trash"></i></button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="6">No employees yet.</td></tr>'}</tbody>
    </table>`);
}

function employeeForm(id){
  const emp=id?db.employees.find(x=>x.id===id):{name:'',phone:'',designation:'',loginEnabled:false,username:'',password:'',assignedHeads:[]};
  const checks=db.heads.length>0?db.heads.map(h=>`<label class="chk"><input type="checkbox" value="${h.id}" ${(emp.assignedHeads||[]).includes(h.id)?'checked':''}><span class="badge ${h.type==='Expense'?'badge-expense':'badge-income'}" style="margin-right:4px;font-size:10px">${h.type[0]}</span>${h.name}</label>`).join(''):'<p style="font-size:13px;color:#888">Add heads first.</p>';
  showModal(`<div class="modal-header"><h3>${id?'Edit':'Add'} Employee</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveEmployee(event,'${id||''}')">
      <label>Full Name *</label><input id="eName" value="${emp.name||''}" required>
      <div class="row-inline">
        <div><label>Phone / WhatsApp</label><input id="ePhone" value="${emp.phone||''}" placeholder="923001234567"></div>
        <div><label>Designation</label><input id="eDesig" value="${emp.designation||''}"></div>
      </div>
      <hr class="section-divider">
      <label><input type="checkbox" id="eLogin" ${emp.loginEnabled?'checked':''} onchange="toggleLoginFields()"> Enable System Login</label>
      <div id="loginFields" style="${emp.loginEnabled?'':'display:none'}">
        <div class="row-inline">
          <div><label>Username</label><input id="eUser" value="${emp.username||''}"></div>
          <div><label>Password</label><input id="ePass" type="password" placeholder="${id?'Leave blank to keep current':''}"></div>
        </div>
        <label>Allowed Account Heads</label>
        <div class="checklist">${checks}</div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
      </div>
    </form>`);
}

function toggleLoginFields(){document.getElementById('loginFields').style.display=document.getElementById('eLogin').checked?'block':'none';}

async function saveEmployee(e,id){
  e.preventDefault();
  const assignedHeads=[...document.querySelectorAll('#loginFields input[type=checkbox]:checked')].map(c=>c.value);
  const data={name:document.getElementById('eName').value.trim(),phone:document.getElementById('ePhone').value.trim(),designation:document.getElementById('eDesig').value.trim(),loginEnabled:document.getElementById('eLogin').checked,username:document.getElementById('eUser').value.trim(),password:document.getElementById('ePass').value,assignedHeads};
  if(!data.name){toast('Name required.','error');return;}
  if(!await api('employees'+(id?'/'+id:''),id?'PUT':'POST',data))return;
  await loadState();closeModal();render();toast('Employee saved.');
}

async function deleteEmployee(id){
  const emp=db.employees.find(x=>x.id===id);
  if(!confirm(`Delete "${emp.name}"?`))return;
  if(!await api('employees/'+id,'DELETE'))return;
  await loadState();render();toast('Employee deleted.','warn');
}

/* ═══════════════════════════════════════════════════
   VOUCHERS
═══════════════════════════════════════════════════ */
let vf={q:'',type:'All',head:'All',sub:'All',from:'',to:'',page:1,perPage:20};

function filteredVouchers(){
  const q=vf.q.toLowerCase();
  return db.vouchers.filter(v=>{
    if(vf.type!=='All'&&v.type!==vf.type)return false;
    if(vf.head!=='All'&&v.headId!==vf.head)return false;
    if(vf.sub!=='All'&&v.subHeadId!==vf.sub)return false;
    if(vf.from&&v.date<vf.from)return false;
    if(vf.to&&v.date>vf.to)return false;
    if(q){
      const h=db.heads.find(x=>x.id===v.headId);
      const sub=(db.subHeads||[]).find(s=>s.id===v.subHeadId);
      if(!`${v.no} ${v.party||''} ${v.description||''} ${h?.name||''} ${sub?.name||''} ${v.createdBy||''}`.toLowerCase().includes(q))return false;
    }
    return true;
  }).sort((a,b)=>(b.date||'').localeCompare(a.date||''));
}

function headLabel(v){
  const h=db.heads.find(x=>x.id===v.headId)||{name:'—'};
  const sub=(db.subHeads||[]).find(s=>s.id===v.subHeadId);
  return h.name+(sub?` <span style="color:#8896a6;font-size:11.5px">→ ${sub.name}</span>`:'');
}

function voucherRowHtml(v){
  const badge=v.type==='Payment'?'<span class="badge badge-expense">Payment</span>':'<span class="badge badge-income">Receipt</span>';
  return `<tr><td>${dateStr(v.date)}</td><td><strong>${v.no}</strong></td><td>${headLabel(v)}</td><td>${badge}</td><td>${v.party||'—'}</td><td style="color:#666;font-size:13px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${v.description||'—'}</td><td style="white-space:nowrap"><strong>${money(v.amount)}</strong></td><td style="color:#888;font-size:12px">${v.createdBy}</td>
  <td style="white-space:nowrap">${v.attachment?`<a class="link-btn" href="${API_BASE}vouchers/${v.id}/attachment" target="_blank" title="${v.attachment}"><i class="fa-solid fa-paperclip"></i></a> `:''}<button class="link-btn" onclick="openVoucherPrint('${v.id}')"><i class="fa-solid fa-print"></i></button> <button class="link-btn" onclick="voucherForm('${v.id}')"><i class="fa-solid fa-pen-to-square"></i></button> <button class="link-btn danger" onclick="deleteVoucher('${v.id}')"><i class="fa-solid fa-trash"></i></button></td></tr>`;
}

function vouchCountText(rows){
  const pay=rows.filter(v=>v.type==='Payment').reduce((s,v)=>s+v.amount,0);
  const rec=rows.filter(v=>v.type==='Receipt').reduce((s,v)=>s+v.amount,0);
  return `Showing ${rows.length} of ${db.vouchers.length} vouchers — Payments ${money(pay)} • Receipts ${money(rec)}`;
}

function vfSubOptions(){
  const subs=(db.subHeads||[]).filter(s=>vf.head==='All'||s.headId===vf.head);
  return `<option value="All">All Sub-Heads</option>`+subs.map(s=>{
    const h=vf.head==='All'?(db.heads.find(x=>x.id===s.headId)||{}).name:null;
    return `<option value="${s.id}" ${vf.sub===s.id?'selected':''}>${s.name}${h?' — '+h:''}</option>`;
  }).join('');
}

function vouchPageSlice(rows){
  const per=vf.perPage==='all'?Math.max(rows.length,1):vf.perPage;
  const pages=Math.max(1,Math.ceil(rows.length/per));
  if(vf.page>pages)vf.page=pages;
  return {slice:rows.slice((vf.page-1)*per,vf.page*per),pages};
}

function vouchPagerHtml(total,pages){
  const nums=[];
  const windowSet=[...new Set([1,pages,vf.page-1,vf.page,vf.page+1])].filter(n=>n>=1&&n<=pages).sort((a,b)=>a-b);
  let prev=0;
  for(const n of windowSet){
    if(n-prev>1)nums.push('<span class="pg-gap">…</span>');
    nums.push(`<button class="pg-btn${n===vf.page?' active':''}" onclick="vfPage(${n})">${n}</button>`);
    prev=n;
  }
  return `<button class="pg-btn" ${vf.page<=1?'disabled':''} onclick="vfPage(${vf.page-1})"><i class="fa-solid fa-chevron-left"></i></button>${nums.join('')}<button class="pg-btn" ${vf.page>=pages?'disabled':''} onclick="vfPage(${vf.page+1})"><i class="fa-solid fa-chevron-right"></i></button><span class="pg-info">Page ${vf.page} of ${pages} • ${total} voucher${total===1?'':'s'}</span>`;
}

function renderVouchTable(){
  const rows=filteredVouchers();
  const {slice,pages}=vouchPageSlice(rows);
  document.getElementById('vouchBody').innerHTML=slice.map(voucherRowHtml).join('')||'<tr class="empty-row"><td colspan="9">No vouchers match the filters.</td></tr>';
  document.getElementById('vouchCount').textContent=vouchCountText(rows);
  document.getElementById('vouchPager').innerHTML=vouchPagerHtml(rows.length,pages);
}

function vfPage(n){vf.page=n;renderVouchTable();}
function vfSetPer(v){vf.perPage=v==='all'?'all':parseInt(v);vf.page=1;renderVouchTable();}

function applyVoucherFilters(){
  const subSel=document.getElementById('vfSub');
  vf={q:document.getElementById('vfQ').value.trim(),type:document.getElementById('vfType').value,
      head:document.getElementById('vfHead').value,sub:subSel.value||'All',
      from:document.getElementById('vfFrom').value,to:document.getElementById('vfTo').value,
      page:1,perPage:vf.perPage};
  subSel.innerHTML=vfSubOptions();
  if(!subSel.value)subSel.value='All';
  vf.sub=subSel.value;
  renderVouchTable();
}

function clearVoucherFilters(){
  vf={q:'',type:'All',head:'All',sub:'All',from:'',to:'',page:1,perPage:20};
  render();
}

function renderVouchersPage(){
  const rows=filteredVouchers();
  const {slice,pages}=vouchPageSlice(rows);
  const perOpts=[10,20,50,100].map(n=>`<option value="${n}" ${vf.perPage===n?'selected':''}>${n}</option>`).join('')+`<option value="all" ${vf.perPage==='all'?'selected':''}>All</option>`;
  const headOpts=db.heads.map(h=>`<option value="${h.id}" ${vf.head===h.id?'selected':''}>${h.name}</option>`).join('');
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-receipt"></i> Vouchers</h2><button class="btn-primary" onclick="voucherForm()"><i class="fa-solid fa-plus"></i> New Voucher</button></div>
    <div class="page-title-bar" style="padding:14px 18px">
      <div class="row-inline" style="width:100%;align-items:flex-end">
        <div style="flex:2.2;min-width:170px"><label>Search</label><input id="vfQ" placeholder="No., party, description…" value="${vf.q.replace(/"/g,'&quot;')}" oninput="applyVoucherFilters()"></div>
        <div style="min-width:110px"><label>Type</label><select id="vfType" onchange="applyVoucherFilters()">
          <option ${vf.type==='All'?'selected':''}>All</option><option ${vf.type==='Payment'?'selected':''}>Payment</option><option ${vf.type==='Receipt'?'selected':''}>Receipt</option></select></div>
        <div style="min-width:140px"><label>Head</label><select id="vfHead" onchange="applyVoucherFilters()"><option value="All">All Heads</option>${headOpts}</select></div>
        <div style="min-width:150px"><label>Sub-Head</label><select id="vfSub" onchange="applyVoucherFilters()">${vfSubOptions()}</select></div>
        <div style="min-width:130px"><label>From</label><input type="date" id="vfFrom" value="${vf.from}" onchange="applyVoucherFilters()"></div>
        <div style="min-width:130px"><label>To</label><input type="date" id="vfTo" value="${vf.to}" onchange="applyVoucherFilters()"></div>
        <div style="flex:0;min-width:auto"><button class="btn-secondary" onclick="clearVoucherFilters()"><i class="fa-solid fa-rotate-left"></i> Reset</button></div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 10px 4px">
      <div id="vouchCount" style="font-size:12px;color:#8896a6">${vouchCountText(rows)}</div>
      <label style="margin:0;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#5b6b7d;text-transform:none;letter-spacing:0">Show
        <select id="vfPer" style="width:auto;margin-top:0;padding:5px 8px;font-size:12.5px" onchange="vfSetPer(this.value)">${perOpts}</select> per page</label>
    </div>
    <table class="list-table">
      <thead><tr><th>Date</th><th>No.</th><th>Head</th><th>Type</th><th>Party</th><th>Description</th><th>Amount</th><th>By</th><th>Actions</th></tr></thead>
      <tbody id="vouchBody">${slice.map(voucherRowHtml).join('')||'<tr class="empty-row"><td colspan="9">No vouchers match the filters.</td></tr>'}</tbody>
    </table>
    <div class="pager" id="vouchPager">${vouchPagerHtml(rows.length,pages)}</div>`);
}

function renderAddPage(){
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-circle-plus"></i> Add Entry</h2></div>
    <div class="s-card" style="max-width:400px;text-align:center;padding:30px">
      <div style="font-size:48px;margin-bottom:12px;color:var(--secondary)"><i class="fa-solid fa-receipt"></i></div>
      <h3 style="color:var(--primary);margin-bottom:8px">Record a Transaction</h3>
      <p style="color:#888;margin:0 0 20px">Record payments or receipts for your assigned account heads.</p>
      <button class="btn-primary" style="width:100%;padding:13px" onclick="voucherForm()"><i class="fa-solid fa-circle-plus"></i> New Entry</button>
    </div>`);
}

function renderMinePage(){
  const rows=[...db.vouchers].filter(v=>v.createdBy===session.name).sort((a,b)=>(b.date||'').localeCompare(a.date||''));
  const total=rows.reduce((s,v)=>s+v.amount,0);
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-clipboard-list"></i> My Entries</h2><span class="badge badge-neutral">${rows.length} records</span></div>
    <table class="list-table">
      <thead><tr><th>Date</th><th>No.</th><th>Head</th><th>Type</th><th>Amount</th><th></th></tr></thead>
      <tbody>${rows.map(v=>{
        return `<tr><td>${dateStr(v.date)}</td><td>${v.no}</td><td>${headLabel(v)}</td><td>${v.type==='Payment'?'<span class="badge badge-expense">Payment</span>':'<span class="badge badge-income">Receipt</span>'}</td><td><strong>${money(v.amount)}</strong></td><td><button class="link-btn" onclick="openVoucherPrint('${v.id}')"><i class="fa-solid fa-print"></i> View</button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="6">No entries yet.</td></tr>'}
      ${rows.length?`<tr style="background:#f7f9fc"><td colspan="4" style="text-align:right;font-weight:700;padding:12px 14px">Total</td><td style="font-weight:800;padding:12px 14px">${money(total)}</td><td></td></tr>`:''}</tbody>
    </table>`);
}

function voucherForm(id){
  const editing=id?db.vouchers.find(x=>x.id===id):null;
  const allowedHeads=session.role==='admin'?db.heads:db.heads.filter(h=>(currentEmployee()?.assignedHeads||[]).includes(h.id));
  if(!allowedHeads.length){toast('No account heads assigned. Contact admin.','warn');return;}
  const options=allowedHeads.map(h=>`<option value="${h.id}" data-type="${h.type}" ${editing&&editing.headId===h.id?'selected':''}>${h.name} — ${h.type}${h.group?' ('+h.group+')':''}</option>`).join('');
  showModal(`<div class="modal-header"><h3>${id?'Edit':'New'} Voucher / Entry</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveVoucher(event,'${id||''}')">
      <label>Account Head *</label><select id="vHead" required onchange="vTypeLabel();vSubSync()">${options}</select>
      <div id="vSubWrap" style="display:none"><label>Sub-Head *</label><select id="vSubHead"></select></div>
      <div id="vTypeShow" style="margin-top:8px;padding:9px 13px;border-radius:7px;font-size:13px;font-weight:600;display:none"></div>
      <div class="row-inline">
        <div><label>Date *</label><input type="date" id="vDate" value="${editing?.date||new Date().toISOString().slice(0,10)}" required></div>
        <div><label>Amount (PKR) *</label><input type="number" id="vAmount" min="1" value="${editing?.amount||''}" placeholder="0" required></div>
      </div>
      <label id="vPartyLabel">Paid To / Received From</label>
      <input id="vParty" value="${editing?.party||''}" placeholder="Person or company name">
      <label>Description / Narration</label>
      <textarea id="vDesc" placeholder="Details about this transaction">${editing?.description||''}</textarea>
      <label>Attachment (image or PDF, max 5 MB)</label>
      <input type="file" id="vAttach" accept="image/*,.pdf">
      ${editing?.attachment?`<p style="font-size:13px;margin-top:6px"><i class="fa-solid fa-paperclip"></i> <a href="${API_BASE}vouchers/${id}/attachment" target="_blank">${editing.attachment}</a>
        &nbsp;<label style="display:inline;font-weight:400;text-transform:none;letter-spacing:0;margin:0"><input type="checkbox" id="vAttachRemove" style="margin:0 4px 0 0"> Remove</label></p>`:''}
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save & Generate</button>
      </div>
    </form>`);
  vTypeLabel();
  vSubSync(editing?.subHeadId||'');
}

function vSubSync(pre=''){
  const headId=document.getElementById('vHead').value;
  const subs=(db.subHeads||[]).filter(s=>s.headId===headId);
  const wrap=document.getElementById('vSubWrap'),sel=document.getElementById('vSubHead');
  wrap.style.display=subs.length?'block':'none';
  sel.required=!!subs.length;
  sel.innerHTML=subs.length?`<option value="">— Select sub-head —</option>`+subs.map(s=>`<option value="${s.id}" ${s.id===pre?'selected':''}>${s.name}</option>`).join(''):'';
}

function vTypeLabel(){
  const sel=document.getElementById('vHead');
  if(!sel?.options[sel.selectedIndex])return;
  const type=sel.options[sel.selectedIndex].dataset.type;
  const show=document.getElementById('vTypeShow');
  if(!type){show.style.display='none';return;}
  show.style.display='block';
  if(type==='Expense'){show.style.background='#fef0ef';show.style.color='#c0392b';show.innerHTML='<i class="fa-solid fa-arrow-up"></i> PAYMENT VOUCHER — money going out';document.getElementById('vPartyLabel').textContent='Paid To';}
  else{show.style.background='#edfaf2';show.style.color='#1e8449';show.innerHTML='<i class="fa-solid fa-arrow-down"></i> RECEIPT VOUCHER — money coming in';document.getElementById('vPartyLabel').textContent='Received From';}
}

async function saveVoucher(e,id){
  e.preventDefault();
  const sel=document.getElementById('vHead');
  if(!sel.value){toast('Select an account head.','warn');return;}
  const subWrap=document.getElementById('vSubWrap');
  const subHeadId=subWrap&&subWrap.style.display!=='none'?document.getElementById('vSubHead').value:'';
  if(subWrap&&subWrap.style.display!=='none'&&!subHeadId){toast('Select a sub-head.','warn');return;}
  const data={headId:sel.value,subHeadId,date:document.getElementById('vDate').value,amount:Number(document.getElementById('vAmount').value),
    party:document.getElementById('vParty').value.trim(),description:document.getElementById('vDesc').value.trim()};
  const file=document.getElementById('vAttach')?.files[0];
  if(file){
    if(file.size>5*1024*1024){toast('Attachment too large (max 5 MB).','warn');return;}
    data.attachment={name:file.name,data:await readFileData(file)};
  }
  if(document.getElementById('vAttachRemove')?.checked)data.removeAttachment=true;
  const r=await api('vouchers'+(id?'/'+id:''),id?'PUT':'POST',data);
  if(!r)return;
  await loadState();closeModal();
  if(id){toast('Voucher updated.');openVoucherPrint(id);}
  else{toast('Voucher created!');openVoucherPrint(r.voucher.id);}
}

async function deleteVoucher(id){
  const v=db.vouchers.find(x=>x.id===id);
  if(!confirm(`Delete voucher ${v.no}?`))return;
  if(!await api('vouchers/'+id,'DELETE'))return;
  await loadState();render();toast('Voucher deleted.','warn');
}

function openVoucherPrint(id){
  const v=db.vouchers.find(x=>x.id===id);if(!v)return;
  const h=db.heads.find(x=>x.id===v.headId)||{name:'Unknown',type:''};
  printFilename=v.no;
  printContent=`<div id="docArea" class="doc">${docHeader()}
    <h2 style="text-align:center;margin:18px 0;color:var(--primary);text-decoration:underline">${v.type.toUpperCase()} VOUCHER</h2>
    <table class="doc-table" style="margin-bottom:12px"><tr><td style="width:50%"><b>Voucher No.:</b> <strong style="font-size:16px">${v.no}</strong></td><td><b>Date:</b> ${dateStr(v.date)}</td></tr></table>
    <table class="doc-table">
      <tr><th style="width:35%">Account Head</th><td>${h.name}${h.type?' ('+h.type+')':''}${(()=>{const sub=(db.subHeads||[]).find(s=>s.id===v.subHeadId);return sub?` → <strong>${sub.name}</strong>`:'';})()}</td></tr>
      <tr><th>${v.type==='Payment'?'Paid To':'Received From'}</th><td>${v.party||'—'}</td></tr>
      <tr><th>Description</th><td>${v.description||'—'}</td></tr>
      <tr><th>Amount</th><td style="font-size:22px;font-weight:800;color:var(--primary)">${money(v.amount)}</td></tr>
    </table>
    ${v.attachment?(()=>{const isImg=/\.(jpe?g|png|gif|webp)$/i.test(v.attachment);const url=`${API_BASE}vouchers/${v.id}/attachment`;
      return isImg
        ?`<div style="margin-top:18px"><p style="font-size:12px;color:#888;margin-bottom:6px"><i class="fa-solid fa-paperclip"></i> Attachment — ${v.attachment}</p><img src="${url}" alt="attachment" style="max-width:100%;border:1px solid var(--border);border-radius:8px"></div>`
        :`<p class="noprint" style="margin-top:14px;font-size:13px"><i class="fa-solid fa-paperclip"></i> Attachment: <a href="${url}" target="_blank">${v.attachment}</a></p>`;})():''}
    <div class="sign-row">
      <div><span>Prepared By</span>${v.createdBy}</div>
      <div><span>Checked By</span>&nbsp;</div>
      <div><span>Approved By</span>&nbsp;</div>
    </div>
    <p style="text-align:center;font-size:11px;color:#bbb;margin-top:30px">Generated by ${db.settings.companyName} ERP • ${new Date().toLocaleString()}</p>
  </div>`;
  printReturnRoute=route;route='print';render();
}

/* ═══════════════════════════════════════════════════
   INVOICES
═══════════════════════════════════════════════════ */
function renderInvoicesPage(){
  const sorted=[...db.invoices].sort((a,b)=>(b.date||'').localeCompare(a.date||''));
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-file-invoice"></i> Invoices</h2><button class="btn-primary" onclick="invoiceForm()"><i class="fa-solid fa-plus"></i> New Invoice</button></div>
    <table class="list-table">
      <thead><tr><th>Date</th><th>No.</th><th>Client</th><th>Items</th><th>Total</th><th>Actions</th></tr></thead>
      <tbody>${sorted.map(inv=>{
        const total=inv.items.reduce((s,i)=>s+(i.qty*i.rate),0);
        return `<tr><td>${dateStr(inv.date)}</td><td><strong>${inv.no}</strong></td><td>${inv.client}</td><td><span class="badge badge-neutral">${inv.items.length}</span></td><td><strong>${money(total)}</strong></td>
        <td><button class="link-btn" onclick="openInvoicePrint('${inv.id}')"><i class="fa-solid fa-print"></i></button> <button class="link-btn" onclick="invoiceForm('${inv.id}')"><i class="fa-solid fa-pen-to-square"></i></button> <button class="link-btn danger" onclick="deleteInvoice('${inv.id}')"><i class="fa-solid fa-trash"></i></button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="6">No invoices yet.</td></tr>'}</tbody>
    </table>`);
}

function invoiceForm(id){
  const inv=id?db.invoices.find(x=>x.id===id):{client:'',address:'',date:new Date().toISOString().slice(0,10),items:[{desc:'',qty:1,rate:0}],notes:''};
  tempInvoiceItems=JSON.parse(JSON.stringify(inv.items||[{desc:'',qty:1,rate:0}]));
  showModal(`<div class="modal-header"><h3>${id?'Edit':'New'} Invoice</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveInvoice(event,'${id||''}')">
      <div class="row-inline">
        <div><label>Client Name *</label><input id="iClient" value="${inv.client||''}" required></div>
        <div><label>Date</label><input type="date" id="iDate" value="${inv.date||new Date().toISOString().slice(0,10)}"></div>
      </div>
      <label>Client Address</label><input id="iAddress" value="${inv.address||''}">
      <hr class="section-divider">
      <strong style="font-size:13px;color:var(--primary)">LINE ITEMS</strong>
      <div style="display:grid;grid-template-columns:2.5fr 70px 120px 110px 36px;gap:8px;margin-top:8px;padding:0 2px">
        <span style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase">Description</span>
        <span style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase">Qty</span>
        <span style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase">Rate</span>
        <span style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase">Amount</span>
        <span></span>
      </div>
      <div id="itemsWrap">${renderItemsRows()}</div>
      <button type="button" class="btn-secondary" style="margin-top:10px;font-size:13px" onclick="addItemRow()"><i class="fa-solid fa-plus"></i> Add Line Item</button>
      <p style="text-align:right;font-weight:800;font-size:16px;color:var(--primary);margin-top:12px">Total: <span id="invTotalShow">${money(itemsTotal())}</span></p>
      <label>Notes / Terms</label><textarea id="iNotes">${inv.notes||''}</textarea>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save & Generate</button>
      </div>
    </form>`);
}

function renderItemsRows(){
  return tempInvoiceItems.map((it,i)=>`<div class="item-row">
    <input placeholder="Item description" value="${it.desc||''}" oninput="updateItem(${i},'desc',this.value)">
    <input type="number" placeholder="1" value="${it.qty}" min="0" step="any" oninput="updateItem(${i},'qty',this.value)">
    <input type="number" placeholder="0" value="${it.rate}" min="0" step="any" oninput="updateItem(${i},'rate',this.value)">
    <span style="font-size:13px;font-weight:600;color:var(--primary)">${money(it.qty*it.rate)}</span>
    <button type="button" class="link-btn danger" onclick="removeItemRow(${i})"><i class="fa-solid fa-xmark"></i></button>
  </div>`).join('');
}
function addItemRow(){tempInvoiceItems.push({desc:'',qty:1,rate:0});refreshItemsWrap();}
function removeItemRow(i){if(tempInvoiceItems.length===1){toast('Keep at least one item.','warn');return;}tempInvoiceItems.splice(i,1);refreshItemsWrap();}
function updateItem(i,f,v){tempInvoiceItems[i][f]=f==='desc'?v:Number(v)||0;refreshItemsWrap();}
function refreshItemsWrap(){document.getElementById('itemsWrap').innerHTML=renderItemsRows();document.getElementById('invTotalShow').textContent=money(itemsTotal());}
function itemsTotal(){return tempInvoiceItems.reduce((s,i)=>s+(Number(i.qty||0)*Number(i.rate||0)),0);}

async function saveInvoice(e,id){
  e.preventDefault();
  const data={client:document.getElementById('iClient').value.trim(),address:document.getElementById('iAddress').value.trim(),
    date:document.getElementById('iDate').value,items:JSON.parse(JSON.stringify(tempInvoiceItems)),
    notes:document.getElementById('iNotes').value.trim()};
  const r=await api('invoices'+(id?'/'+id:''),id?'PUT':'POST',data);
  if(!r)return;
  await loadState();closeModal();toast('Invoice saved!');
  openInvoicePrint(id||r.invoice.id);
}

async function deleteInvoice(id){
  const inv=db.invoices.find(x=>x.id===id);
  if(!confirm(`Delete invoice ${inv.no}?`))return;
  if(!await api('invoices/'+id,'DELETE'))return;
  await loadState();render();toast('Invoice deleted.','warn');
}

function openInvoicePrint(id){
  const inv=db.invoices.find(x=>x.id===id);if(!inv)return;
  const subtotal=inv.items.reduce((s,i)=>s+(i.qty*i.rate),0);
  const taxRate=Number(db.settings.taxRate||0);
  const tax=subtotal*(taxRate/100);
  const total=subtotal+tax;
  printFilename=inv.no;
  printContent=`<div id="docArea" class="doc">${docHeader(`Invoice: <strong>${inv.no}</strong>`)}
    <h2 style="text-align:center;margin:18px 0;color:var(--primary)">INVOICE</h2>
    <table class="doc-table" style="margin-bottom:14px">
      <tr><td style="width:50%"><b>Bill To:</b><br><strong style="font-size:15px">${inv.client}</strong><br>${inv.address||''}</td>
      <td><b>Invoice No.:</b> ${inv.no}<br><b>Date:</b> ${dateStr(inv.date)}<br><b>Due:</b> On Receipt</td></tr>
    </table>
    <table class="doc-table">
      <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
      <tbody>${inv.items.map((it,i)=>`<tr><td>${i+1}</td><td>${it.desc}</td><td>${it.qty}</td><td>${money(it.rate)}</td><td><strong>${money(it.qty*it.rate)}</strong></td></tr>`).join('')}</tbody>
      <tfoot>
        <tr><th colspan="4" style="text-align:right">Sub Total</th><th>${money(subtotal)}</th></tr>
        ${taxRate?`<tr><th colspan="4" style="text-align:right">Tax (${taxRate}%)</th><th>${money(tax)}</th></tr>`:''}
        <tr><th colspan="4" style="text-align:right;font-size:15px">TOTAL</th><th style="font-size:17px;color:var(--primary)">${money(total)}</th></tr>
      </tfoot>
    </table>
    ${inv.notes?`<p style="margin-top:16px;padding:12px 16px;background:#f7f9fc;border-radius:8px;font-size:13px"><b>Notes:</b> ${inv.notes}</p>`:''}
    ${db.settings.invoiceFooter?`<p style="text-align:center;margin-top:16px;color:#888;font-size:13px">${db.settings.invoiceFooter}</p>`:''}
    <div class="sign-row">
      <div><span>Prepared By</span>${session.name}</div>
      <div><span>Authorized Signature</span>&nbsp;</div>
    </div>
  </div>`;
  printReturnRoute=route;route='print';render();
}

/* ═══════════════════════════════════════════════════
   SALARY SLIPS
═══════════════════════════════════════════════════ */
function renderSalaryPage(){
  const sorted=[...db.salary].sort((a,b)=>(b.year-a.year)||(b.month-a.month));
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-money-bill-wave"></i> Salary Slips</h2><button class="btn-primary" onclick="salaryForm()"><i class="fa-solid fa-plus"></i> Generate Slip</button></div>
    <table class="list-table">
      <thead><tr><th>Month</th><th>Employee</th><th>Basic</th><th>Deductions</th><th>Bonus</th><th>Net Payable</th><th>Actions</th></tr></thead>
      <tbody>${sorted.map(s=>{
        const mn=['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][s.month];
        return `<tr><td><strong>${mn} ${s.year}</strong></td><td>${s.employeeName}</td><td>${money(s.basic)}</td><td style="color:var(--danger)">-${money(s.deduction)}</td><td style="color:var(--success)">+${money(s.bonus)}</td><td><strong style="font-size:15px">${money(s.total)}</strong></td>
        <td><button class="link-btn" onclick="openSalaryPrint('${s.id}')"><i class="fa-solid fa-print"></i></button> <button class="link-btn" onclick="salaryForm('${s.id}')"><i class="fa-solid fa-pen-to-square"></i></button> <button class="link-btn danger" onclick="deleteSalary('${s.id}')"><i class="fa-solid fa-trash"></i></button></td></tr>`;
      }).join('')||'<tr class="empty-row"><td colspan="7">No salary slips yet.</td></tr>'}</tbody>
    </table>`);
}

function monthOptions(sel){
  return ['January','February','March','April','May','June','July','August','September','October','November','December'].map((n,i)=>`<option value="${i+1}" ${sel==i+1?'selected':''}>${n}</option>`).join('');
}

function salaryForm(id){
  const s=id?db.salary.find(x=>x.id===id):{employeeId:'',employeeName:'',designation:'',phone:'',month:new Date().getMonth()+1,year:new Date().getFullYear(),basic:0,allowance:0,deduction:0,bonus:0};
  const empOpts=db.employees.map(e=>`<option value="${e.id}" ${s.employeeId===e.id?'selected':''}>${e.name}${e.designation?' - '+e.designation:''}</option>`).join('');
  showModal(`<div class="modal-header"><h3>${id?'Edit':'Generate'} Salary Slip</h3><button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <form onsubmit="saveSalary(event,'${id||''}')">
      <label>Select Employee (optional)</label>
      <select id="sEmp" onchange="fillEmpSalary()"><option value="">— Select from list —</option>${empOpts}</select>
      <div class="row-inline">
        <div><label>Name *</label><input id="sName" value="${s.employeeName||''}" required></div>
        <div><label>Designation</label><input id="sDesig" value="${s.designation||''}"></div>
      </div>
      <label>WhatsApp Number</label><input id="sPhone" value="${s.phone||''}" placeholder="923001234567">
      <div class="row-inline">
        <div><label>Month</label><select id="sMonth">${monthOptions(s.month)}</select></div>
        <div><label>Year</label><input type="number" id="sYear" value="${s.year}" min="2000" max="2100"></div>
      </div>
      <div class="row-inline">
        <div><label>Basic Salary</label><input type="number" id="sBasic" value="${s.basic}" min="0" oninput="calcSalTotal()"></div>
        <div><label>Allowances</label><input type="number" id="sAllow" value="${s.allowance||0}" min="0" oninput="calcSalTotal()"></div>
      </div>
      <div class="row-inline">
        <div><label>Deductions</label><input type="number" id="sDeduct" value="${s.deduction}" min="0" oninput="calcSalTotal()"></div>
        <div><label>Bonus</label><input type="number" id="sBonus" value="${s.bonus}" min="0" oninput="calcSalTotal()"></div>
      </div>
      <div style="background:#f0f4ff;padding:14px;border-radius:8px;margin-top:14px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:#555">Net Payable</span>
        <span id="sTotalShow" style="font-size:22px;font-weight:800;color:var(--primary)">${money((s.basic||0)+(s.allowance||0)-(s.deduction||0)+(s.bonus||0))}</span>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save & Generate</button>
      </div>
    </form>`);
}

function fillEmpSalary(){
  const emp=db.employees.find(e=>e.id===document.getElementById('sEmp').value);
  if(emp){document.getElementById('sName').value=emp.name;document.getElementById('sPhone').value=emp.phone||'';document.getElementById('sDesig').value=emp.designation||'';}
}
function calcSalTotal(){
  const b=Number(document.getElementById('sBasic').value||0);
  const a=Number(document.getElementById('sAllow').value||0);
  const d=Number(document.getElementById('sDeduct').value||0);
  const bo=Number(document.getElementById('sBonus').value||0);
  document.getElementById('sTotalShow').textContent=money(b+a-d+bo);
}

async function saveSalary(e,id){
  e.preventDefault();
  const data={employeeId:document.getElementById('sEmp').value||'',employeeName:document.getElementById('sName').value.trim(),
    designation:document.getElementById('sDesig').value.trim(),phone:document.getElementById('sPhone').value.trim(),
    month:Number(document.getElementById('sMonth').value),year:Number(document.getElementById('sYear').value),
    basic:Number(document.getElementById('sBasic').value||0),allowance:Number(document.getElementById('sAllow').value||0),
    deduction:Number(document.getElementById('sDeduct').value||0),bonus:Number(document.getElementById('sBonus').value||0)};
  const r=await api('salary'+(id?'/'+id:''),id?'PUT':'POST',data);
  if(!r)return;
  await loadState();closeModal();toast('Salary slip saved!');
  openSalaryPrint(id||r.slip.id);
}

async function deleteSalary(id){
  const s=db.salary.find(x=>x.id===id);
  if(!confirm(`Delete slip for ${s.employeeName}?`))return;
  if(!await api('salary/'+id,'DELETE'))return;
  await loadState();render();toast('Slip deleted.','warn');
}

function openSalaryPrint(id){
  const s=db.salary.find(x=>x.id===id);if(!s)return;
  const mn=['','January','February','March','April','May','June','July','August','September','October','November','December'][s.month];
  printFilename=`Salary-${s.employeeName}-${mn}-${s.year}`;
  const waMsg=encodeURIComponent(`Dear ${s.employeeName},\n\nSalary Slip — ${mn} ${s.year}\nBasic: ${money(s.basic)}\nAllowances: ${money(s.allowance||0)}\nDeductions: -${money(s.deduction)}\nBonus: +${money(s.bonus)}\nNet Payable: ${money(s.total)}\n\n— ${db.settings.companyName}`);
  printContent=`<div id="docArea" class="doc">${docHeader()}
    <h2 style="text-align:center;margin:18px 0;color:var(--primary)">SALARY SLIP</h2>
    <h3 style="text-align:center;color:#666;margin-bottom:20px;font-weight:400">${mn} ${s.year}</h3>
    <table class="doc-table">
      <tr><th style="width:35%">Employee Name</th><td><strong>${s.employeeName}</strong></td></tr>
      ${s.designation?`<tr><th>Designation</th><td>${s.designation}</td></tr>`:''}
      <tr><th>Pay Period</th><td>${mn} ${s.year}</td></tr>
    </table>
    <table class="doc-table" style="margin-top:16px">
      <thead><tr><th>Earnings</th><th>Amount</th><th>Deductions</th><th>Amount</th></tr></thead>
      <tbody>
        <tr><td>Basic Salary</td><td>${money(s.basic)}</td><td>Deductions</td><td>${money(s.deduction)}</td></tr>
        <tr><td>Allowances</td><td>${money(s.allowance||0)}</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr><td>Bonus</td><td>${money(s.bonus)}</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      </tbody>
    </table>
    <div style="text-align:right;margin-top:14px;padding:14px;background:var(--primary);color:#fff;border-radius:8px">
      NET PAYABLE &nbsp;<span style="font-size:24px;font-weight:800">${money(s.total)}</span>
    </div>
    <div class="sign-row">
      <div><span>Employee Signature</span>&nbsp;</div>
      <div><span>HR / Accounts</span>&nbsp;</div>
      <div><span>Authorized By</span>&nbsp;</div>
    </div>
    ${s.phone?`<div class="noprint" style="margin-top:20px;text-align:center;padding:16px;background:#f0f4ff;border-radius:8px">
      <p style="font-weight:600;margin-bottom:10px"><i class="fa-brands fa-whatsapp"></i> Send via WhatsApp</p>
      <button class="btn-primary" onclick="window.open('https://wa.me/${s.phone.replace(/\D/g,'')}?text=${waMsg}','_blank')"><i class="fa-brands fa-whatsapp"></i> Open WhatsApp</button>
      <p style="font-size:12px;color:#888;margin-top:8px">Tip: Download PDF first, then attach it in the chat.</p>
    </div>`:''}
  </div>`;
  printReturnRoute=route;route='print';render();
}

/* ═══════════════════════════════════════════════════
   REPORTS
═══════════════════════════════════════════════════ */
function renderReportsPage(){
  const headChecks=db.heads.map(h=>`<label class="chk"><input type="checkbox" class="repHeadChk" value="${h.id}" checked onchange="repHeadSync()">${h.name} (${h.type})</label>`).join('');
  const subChecks=(db.subHeads||[]).map(s=>`<label class="chk"><input type="checkbox" class="repSubChk" value="${s.id}" checked onchange="repSubSync()">${s.name}</label>`).join('');
  return shell(`
    <div class="page-header noprint"><h2><i class="fa-solid fa-chart-line"></i> Reports & Analytics</h2></div>
    <div class="noprint" style="background:#fff;padding:22px;border-radius:12px;margin-bottom:22px;box-shadow:var(--shadow)">
      <strong style="font-size:14px;color:var(--primary);display:block;margin-bottom:14px"><i class="fa-solid fa-filter"></i> Filter Options</strong>
      <div class="row-inline">
        <div><label>Period</label>
          <select id="repPeriod" onchange="toggleCustomDates()">
            <option value="today">Today</option><option value="week">This Week</option>
            <option value="month" selected>This Month</option><option value="quarter">This Quarter</option>
            <option value="semi">Semi-Annual</option><option value="year">This Year</option>
            <option value="custom">Custom Range</option>
          </select>
        </div>
        <div id="repFromWrap" style="display:none"><label>From</label><input type="date" id="repFrom"></div>
        <div id="repToWrap" style="display:none"><label>To</label><input type="date" id="repTo"></div>
        <div><label>Type</label><select id="repType"><option>All</option><option>Expense</option><option>Income</option></select></div>
        <div style="flex:2;min-width:220px"><label>Heads</label>
          <div class="ms-wrap">
            <button type="button" class="ms-btn" onclick="repHeadToggle()">
              <span id="repHeadLabel">All Heads</span><i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="ms-panel hidden" id="repHeadPanel">
              <label class="chk"><input type="checkbox" id="repHeadAll" checked onchange="repHeadToggleAll(this);repHeadSync()">All Heads</label>
              ${headChecks}
            </div>
          </div>
        </div>
        <div style="flex:2;min-width:220px"><label>Sub-Heads</label>
          <div class="ms-wrap">
            <button type="button" class="ms-btn" onclick="repSubToggle()">
              <span id="repSubLabel">All Sub-Heads</span><i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="ms-panel hidden" id="repSubPanel">
              <label class="chk"><input type="checkbox" id="repSubAll" checked onchange="repSubToggleAll(this);repSubSync()">All Sub-Heads</label>
              ${subChecks||'<p style="font-size:12px;color:#8896a6;margin:4px 0">No sub-heads defined.</p>'}
            </div>
          </div>
        </div>
        <div style="display:flex;align-items:flex-end"><button class="btn-primary" style="width:100%" onclick="generateReport()"><i class="fa-solid fa-chart-column"></i> Generate</button></div>
      </div>
    </div>
    <div id="reportResults"></div>`);
}

function toggleCustomDates(){
  const show=document.getElementById('repPeriod').value==='custom';
  document.getElementById('repFromWrap').style.display=show?'block':'none';
  document.getElementById('repToWrap').style.display=show?'block':'none';
}

function repHeadToggle(){
  document.getElementById('repHeadPanel').classList.toggle('hidden');
}
function repHeadToggleAll(cb){
  document.querySelectorAll('.repHeadChk').forEach(c=>c.checked=cb.checked);
}
function repHeadSync(){
  const boxes=[...document.querySelectorAll('.repHeadChk')];
  const names=boxes.filter(c=>c.checked).map(c=>c.closest('label').textContent.trim());
  document.getElementById('repHeadAll').checked=names.length===boxes.length;
  const lbl=document.getElementById('repHeadLabel');
  if(lbl)lbl.textContent=!names.length||names.length===boxes.length?'All Heads'
    :names.length<=2?names.join(', '):`${names.length} heads selected`;
  repSubRebuild();
}
function repSubToggle(){
  document.getElementById('repSubPanel').classList.toggle('hidden');
}
function repSubToggleAll(cb){
  document.querySelectorAll('.repSubChk').forEach(c=>c.checked=cb.checked);
}
function repSubSync(){
  const boxes=[...document.querySelectorAll('.repSubChk')];
  const names=boxes.filter(c=>c.checked).map(c=>c.closest('label').textContent.trim());
  document.getElementById('repSubAll').checked=names.length===boxes.length;
  const lbl=document.getElementById('repSubLabel');
  if(lbl)lbl.textContent=!boxes.length||!names.length||names.length===boxes.length?'All Sub-Heads'
    :names.length<=2?names.join(', '):`${names.length} sub-heads selected`;
}
function repSubRebuild(){
  const panel=document.getElementById('repSubPanel');
  if(!panel)return;
  const headIds=new Set([...document.querySelectorAll('.repHeadChk:checked')].map(c=>c.value));
  const avail=(db.subHeads||[]).filter(s=>headIds.has(s.headId));
  const prevIds=new Set([...panel.querySelectorAll('.repSubChk')].map(c=>c.value));
  const prevChecked=new Set([...panel.querySelectorAll('.repSubChk:checked')].map(c=>c.value));
  panel.innerHTML=`<label class="chk"><input type="checkbox" id="repSubAll" checked onchange="repSubToggleAll(this);repSubSync()">All Sub-Heads</label>`
    +(avail.length?avail.map(s=>`<label class="chk"><input type="checkbox" class="repSubChk" value="${s.id}" ${!prevIds.has(s.id)||prevChecked.has(s.id)?'checked':''} onchange="repSubSync()">${s.name}</label>`).join('')
      :'<p style="font-size:12px;color:#8896a6;margin:4px 0">No sub-heads for selected heads.</p>');
  repSubSync();
}
document.addEventListener('click',e=>{
  document.querySelectorAll('.ms-panel:not(.hidden)').forEach(p=>{
    if(!p.parentElement.contains(e.target))p.classList.add('hidden');
  });
});

function generateReport(){
  const period=document.getElementById('repPeriod').value;
  const today=new Date();let from,to=today;
  if(period==='today'){from=today;}
  else if(period==='week'){from=new Date(today);from.setDate(today.getDate()-today.getDay());}
  else if(period==='month'){from=new Date(today.getFullYear(),today.getMonth(),1);}
  else if(period==='quarter'){const q=Math.floor(today.getMonth()/3);from=new Date(today.getFullYear(),q*3,1);}
  else if(period==='semi'){from=new Date(today.getFullYear(),today.getMonth()<6?0:6,1);}
  else if(period==='year'){from=new Date(today.getFullYear(),0,1);}
  else{from=new Date(document.getElementById('repFrom').value);to=new Date(document.getElementById('repTo').value);if(!from.getTime()||!to.getTime()){toast('Select both dates.','warn');return;}}
  const fromStr=from.toISOString().slice(0,10),toStr=to.toISOString().slice(0,10);
  const repType=document.getElementById('repType').value;
  const repHeads=[...document.querySelectorAll('.repHeadChk:checked')].map(c=>c.value);
  const repSubs=new Set([...document.querySelectorAll('.repSubChk:checked')].map(c=>c.value));
  const subChkCount=document.querySelectorAll('.repSubChk').length;
  let rows=db.vouchers.filter(v=>v.date>=fromStr&&v.date<=toStr);
  if(repType==='Expense')rows=rows.filter(v=>v.type==='Payment');
  if(repType==='Income')rows=rows.filter(v=>v.type==='Receipt');
  if(repHeads.length&&repHeads.length<db.heads.length)rows=rows.filter(v=>repHeads.includes(v.headId));
  if(repSubs.size<subChkCount)rows=rows.filter(v=>!v.subHeadId||repSubs.has(v.subHeadId));
  rows=[...rows].sort((a,b)=>a.date.localeCompare(b.date));
  currentReportRows=rows;reportMeta={from:fromStr,to:toStr};
  const inc=rows.filter(v=>v.type==='Receipt').reduce((s,v)=>s+v.amount,0);
  const exp=rows.filter(v=>v.type==='Payment').reduce((s,v)=>s+v.amount,0);
  // Head-wise breakdown (splits by sub-head where set)
  const hMap={};
  rows.forEach(v=>{const k=v.headId+'|'+(v.subHeadId||'');if(!hMap[k])hMap[k]={inc:0,exp:0};if(v.type==='Receipt')hMap[k].inc+=v.amount;else hMap[k].exp+=v.amount;});
  const headSubName=k=>{const [hId,sId]=k.split('|');const h=db.heads.find(x=>x.id===hId)||{name:'Unknown'};const sub=(db.subHeads||[]).find(s=>s.id===sId);return h.name+(sub?' → '+sub.name:'');};
  document.getElementById('reportResults').innerHTML=`
    <div class="noprint" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
      <button class="btn-secondary" onclick="downloadPDF('docArea','Report-${fromStr}-to-${toStr}.pdf')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
      <button class="btn-secondary" onclick="exportExcel()"><i class="fa-solid fa-file-excel"></i> Excel</button>
      <button class="btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
    <div id="docArea" class="doc">${docHeader()}
      <h3 style="text-align:center;color:var(--primary)">FINANCIAL REPORT</h3>
      <p style="text-align:center;color:#888;margin-bottom:18px">${dateStr(fromStr)} — ${dateStr(toStr)}</p>
      <div class="summary-cards">
        <div class="s-card income"><div class="label">Total Income</div><div class="value">${money(inc)}</div></div>
        <div class="s-card expense"><div class="label">Total Expense</div><div class="value">${money(exp)}</div></div>
        <div class="s-card net"><div class="label">Net Balance</div><div class="value">${money(inc-exp)}</div></div>
      </div>
      ${Object.keys(hMap).length>1?`<h4 style="margin:14px 0 8px;color:var(--primary)">Head-wise Summary</h4>
      <table class="doc-table"><thead><tr><th>Account Head</th><th>Income</th><th>Expense</th><th>Net</th></tr></thead>
      <tbody>${Object.entries(hMap).map(([k,d])=>`<tr><td>${headSubName(k)}</td><td style="white-space:nowrap">${money(d.inc)}</td><td style="white-space:nowrap">${money(d.exp)}</td><td style="font-weight:700;white-space:nowrap">${money(d.inc-d.exp)}</td></tr>`).join('')}</tbody></table>`:''}
      <h4 style="margin:18px 0 8px;color:var(--primary)">All Transactions (${rows.length})</h4>
      <table class="doc-table">
        <thead><tr><th>Date</th><th>Voucher No.</th><th>Head</th><th>Type</th><th>Party</th><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>${rows.map(v=>{
          return `<tr><td>${dateStr(v.date)}</td><td>${v.no}</td><td>${headLabel(v)}</td><td style="color:${v.type==='Payment'?'#c0392b':'#1e8449'};font-weight:600">${v.type}</td><td>${v.party||'—'}</td><td style="font-size:12px;color:#666">${v.description||'—'}</td><td style="text-align:right;font-weight:600;white-space:nowrap;font-size:13px">${money(v.amount)}</td></tr>`;
        }).join('')||'<tr class="empty-row"><td colspan="7">No records found for selected filters.</td></tr>'}
        ${rows.length?`<tr style="background:#f0f3f7"><td colspan="6" style="text-align:right;font-weight:800;padding:12px 14px">TOTAL</td><td style="text-align:right;font-weight:800;padding:12px 14px;white-space:nowrap;font-size:13.5px">${money(rows.reduce((s,v)=>s+v.amount,0))}</td></tr>`:''}</tbody>
      </table>
      <p style="text-align:center;font-size:11px;color:#bbb;margin-top:20px">Generated by ${db.settings.companyName} ERP • ${new Date().toLocaleString()}</p>
    </div>`;
}

function exportExcel(){
  if(!currentReportRows.length){toast('Generate a report first.','warn');return;}
  const data=currentReportRows.map(v=>{const h=db.heads.find(x=>x.id===v.headId)||{name:'',group:''};const sub=(db.subHeads||[]).find(s=>s.id===v.subHeadId);return{Date:v.date,VoucherNo:v.no,Head:h.name,SubHead:sub?.name||'',Group:h.group||'',Type:v.type,Party:v.party||'',Description:v.description||'',Amount:v.amount,CreatedBy:v.createdBy};});
  const ws=XLSX.utils.json_to_sheet(data);
  ws['!cols']=[{wch:12},{wch:18},{wch:22},{wch:18},{wch:14},{wch:12},{wch:20},{wch:30},{wch:14},{wch:18}];
  const wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,ws,'Report');
  XLSX.writeFile(wb,`${db.settings.companyName}-Report-${reportMeta.from}-to-${reportMeta.to}.xlsx`);
  toast('Excel downloaded!');
}

/* ═══════════════════════════════════════════════════
   SETTINGS
═══════════════════════════════════════════════════ */
function renderSettingsPage(){
  const s=db.settings;
  return shell(`
    <div class="page-header"><h2><i class="fa-solid fa-gear"></i> Settings</h2></div>
    <form onsubmit="saveSettings(event)" style="max-width:560px">
      <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px">
        <strong style="color:var(--primary);font-size:14px"><i class="fa-solid fa-building"></i> Company Information</strong>
        <label>Company Name</label><input id="stName" value="${s.companyName}">
        <label>Tagline</label><input id="stTagline" value="${s.tagline||''}">
        <div class="row-inline">
          <div><label>Phone</label><input id="stPhone" value="${s.phone||''}"></div>
          <div><label>Email</label><input id="stEmail" value="${s.email||''}"></div>
        </div>
        <label>Address</label><input id="stAddress" value="${s.address||''}">
        <label>Company Logo (under 400KB)</label>
        <input type="file" accept="image/*" onchange="handleLogoUpload(this)">
        ${s.logo?`<div style="margin-top:8px;display:flex;align-items:center;gap:12px"><img src="${s.logo}" style="width:55px;height:55px;border-radius:10px;object-fit:contain;border:1px solid var(--border)"><button type="button" class="btn-danger" onclick="removeLogo()">Remove Logo</button></div>`:''}
      </div>
      <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px">
        <strong style="color:var(--primary);font-size:14px"><i class="fa-solid fa-file-invoice"></i> Invoice Settings</strong>
        <div class="row-inline">
          <div><label>Currency Symbol</label><input id="stCurrency" value="${s.currency||'Rs'}"></div>
          <div><label>Tax Rate (%)</label><input type="number" id="stTax" value="${s.taxRate||0}" min="0" max="100"></div>
        </div>
        <label>Invoice Footer</label><input id="stInvFooter" value="${s.invoiceFooter||''}">
      </div>
      <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px">
        <strong style="color:var(--primary);font-size:14px"><i class="fa-solid fa-palette"></i> Appearance</strong>
        <div class="row-inline">
          <div><label>Primary Color</label><input type="color" id="stPrimary" value="${s.primary}"></div>
          <div><label>Accent Color</label><input type="color" id="stSecondary" value="${s.secondary}"></div>
        </div>
      </div>
      <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px">
        <strong style="color:var(--primary);font-size:14px"><i class="fa-solid fa-user-shield"></i> Admin Login</strong>
        <div style="background:#fff8e6;border:1px solid #ffd97d;border-radius:6px;padding:10px 14px;margin-top:10px;font-size:13px;color:#9a6800">
          <i class="fa-solid fa-triangle-exclamation"></i> Change carefully — you need these to login as admin.
        </div>
        <div class="row-inline">
          <div><label>Admin Username</label><input id="stAdminUser" placeholder="${session.username||''}"></div>
          <div><label>New Password</label><input type="password" id="stAdminPass" placeholder="Leave blank to keep current"></div>
        </div>
      </div>
      <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:var(--shadow);margin-bottom:16px">
        <strong style="color:var(--primary);font-size:14px"><i class="fa-solid fa-database"></i> Data Backup & Restore</strong>
        <p style="font-size:13px;color:#888;margin-top:8px">Data is stored in this browser. Export weekly to prevent loss.</p>
        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
          <button type="button" class="btn-secondary" onclick="exportBackup()"><i class="fa-solid fa-download"></i> Export Backup (.json)</button>
          <button type="button" class="btn-secondary" onclick="document.getElementById('importFile').click()"><i class="fa-solid fa-upload"></i> Import Backup</button>
          <input type="file" id="importFile" accept=".json" style="display:none" onchange="importBackup(this)">
          <button type="button" class="btn-danger" onclick="resetAllData()"><i class="fa-solid fa-triangle-exclamation"></i> Reset All Data</button>
        </div>
        <p style="font-size:12px;color:#aaa;margin-top:10px">Total records: <strong>${recordCount()}</strong> stored in MySQL</p>
      </div>
      <button class="btn-primary" type="submit" style="width:100%;padding:14px;font-size:15px"><i class="fa-solid fa-floppy-disk"></i> Save All Settings</button>
    </form>`);
}

async function handleLogoUpload(input){
  const file=input.files[0];if(!file)return;
  if(file.size>400000){toast('Logo too large. Use image under 400KB.','warn');return;}
  const reader=new FileReader();
  reader.onload=async e=>{
    if(!await api('settings','PUT',{logo:e.target.result}))return;
    await loadState();render();toast('Logo uploaded.');
  };
  reader.readAsDataURL(file);
}
async function removeLogo(){if(!await api('settings','PUT',{logo:''}))return;await loadState();render();}

async function saveSettings(e){
  e.preventDefault();
  const data={
    companyName:document.getElementById('stName').value.trim()||'BB Builders',
    tagline:document.getElementById('stTagline').value.trim(),
    address:document.getElementById('stAddress').value.trim(),
    phone:document.getElementById('stPhone').value.trim(),
    email:document.getElementById('stEmail').value.trim(),
    primary:document.getElementById('stPrimary').value,
    secondary:document.getElementById('stSecondary').value,
    currency:document.getElementById('stCurrency').value.trim()||'Rs',
    taxRate:Number(document.getElementById('stTax').value||0),
    invoiceFooter:document.getElementById('stInvFooter').value.trim(),
    adminUser:document.getElementById('stAdminUser').value.trim(),
    adminPass:document.getElementById('stAdminPass').value
  };
  if(!await api('settings','PUT',data))return;
  await loadState();applyTheme();render();toast('Settings saved!');
}

async function exportBackup(){
  const data=await api('backup');
  if(!data)return;
  const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download=`BBBuilders-Backup-${new Date().toISOString().slice(0,10)}.json`;
  a.click();toast('Backup file downloaded!');
}

function importBackup(input){
  const file=input.files[0];if(!file)return;
  if(!confirm('Import will REPLACE all current data. Proceed?'))return;
  const reader=new FileReader();
  reader.onload=async e=>{
    try{
      const imported=JSON.parse(e.target.result);
      if(!imported.settings||!imported.vouchers)throw new Error('Invalid file');
      if(!await api('backup/import','POST',imported))return;
      await loadState();applyTheme();render();toast('Data imported successfully!');
    }catch(err){toast('Invalid backup file. Select a valid BB ERP .json backup.','error');}
  };
  reader.readAsText(file);
}

async function resetAllData(){
  if(!confirm('This will delete ALL data permanently. Are you sure?'))return;
  const word=prompt('Type RESET to confirm:');
  if(word!=='RESET'){toast('Reset cancelled.','warn');return;}
  if(!await api('reset','POST'))return;
  db={settings:defaultSettings(),employees:[],heads:[],vouchers:[],invoices:[],salary:[]};
  session=null;route='login';sessionStorage.removeItem('bb_route');render();toast('All data cleared.','warn');
}

/* ═══════════════════════════════════════════════════
   PDF
═══════════════════════════════════════════════════ */
async function downloadPDF(elId,filename){
  const el=document.getElementById(elId);
  if(!el){toast('Nothing to export.','warn');return;}
  toast('Generating PDF...');
  try{
    const scale=2;
    const canvas=await html2canvas(el,{scale,useCORS:true,logging:false,backgroundColor:'#fff'});
    const{jsPDF}=window.jspdf;
    const pdf=new jsPDF('p','pt','a4');
    const pw=pdf.internal.pageSize.getWidth(),ph=pdf.internal.pageSize.getHeight();
    const renderW=canvas.width/scale; // DOM px width as rendered
    const pageDomH=ph*renderW/pw;    // DOM px that fit one PDF page
    const totalDomH=canvas.height/scale;

    // Element ranges (in DOM px, relative to the captured element) that must not be split
    const elRect=el.getBoundingClientRect();
    const ranges=[...el.querySelectorAll('tr,img,h2,h3,h4,p,.s-card,.sign-row,.doc-header,.summary-cards')]
      .map(n=>{const r=n.getBoundingClientRect();return{top:r.top-elRect.top,bottom:r.bottom-elRect.top};});

    let y=0,first=true;
    while(y<totalDomH-1){
      let end=Math.min(y+pageDomH,totalDomH);
      if(end<totalDomH){
        // If an element straddles the cut, move the cut up to its top edge
        let safe=end;
        for(const r of ranges){
          if(r.top<safe-4&&r.bottom>safe+4)safe=Math.min(safe,r.top);
        }
        if(safe>y+10)end=safe;
      }
      const sh=Math.max(1,Math.round((end-y)*scale));
      const page=document.createElement('canvas');
      page.width=canvas.width;page.height=sh;
      page.getContext('2d').drawImage(canvas,0,Math.round(y*scale),canvas.width,sh,0,0,canvas.width,sh);
      if(!first)pdf.addPage();
      pdf.addImage(page.toDataURL('image/png'),'PNG',0,0,pw,sh*pw/canvas.width);
      first=false;
      y=end;
    }
    pdf.save(filename||'Document.pdf');
    toast('PDF downloaded!');
  }catch(err){toast('PDF failed — use Print button instead.','error');console.error(err);}
}

/* ═══════════════════════════════════════════════════
   PRINT VIEW
═══════════════════════════════════════════════════ */
function renderPrintPage(){
  return `<div class="print-bar noprint">
    <button class="btn-secondary" onclick="closePrint()"><i class="fa-solid fa-arrow-left"></i> Back</button>
    <button class="btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    <button class="btn-primary" onclick="downloadPDF('docArea','${(printFilename||'Document').replace(/'/g,'')}.pdf')"><i class="fa-solid fa-download"></i> Download PDF</button>
  </div>${printContent}`;
}
function closePrint(){route=printReturnRoute||savedRoute(session.role);printReturnRoute=null;render();}

/* ═══════════════════════════════════════════════════
   ROUTER
═══════════════════════════════════════════════════ */
function render(){
  applyTheme();
  const app=document.getElementById('app');
  if(!session){app.innerHTML=renderLogin();return;}
  const pages={dashboard:renderDashboard,heads:renderHeadsPage,employees:renderEmployeesPage,vouchers:renderVouchersPage,invoices:renderInvoicesPage,salary:renderSalaryPage,reports:renderReportsPage,settings:renderSettingsPage,add:renderAddPage,mine:renderMinePage,print:renderPrintPage};
  app.innerHTML=(pages[route]||renderDashboard)();
}

/* ── BOOT ── */
async function init(){
  const me=await api('me','GET',undefined,true);
  if(me){
    db.settings=Object.assign({},defaultSettings(),me.settings||{});
    applyTheme();
    if(me.user){session=me.user;route=savedRoute(session.role);sessionStorage.setItem('bb_route',route);await loadState();}
  }
  render();
}
init();