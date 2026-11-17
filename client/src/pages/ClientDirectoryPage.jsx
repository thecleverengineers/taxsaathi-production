import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { PageIntro, PageLoader, EmptyState } from '../components/Ui';
import Icon from '../components/Icons';
import { dateLabel } from '../lib/format';

const blank = {name:'',client_type:'individual',company_name:'',contact_person:'',email:'',phone:''};
export default function ClientDirectoryPage() {
  const [clients,setClients] = useState(null);
  const [editing,setEditing] = useState(null);
  const [form,setForm] = useState(blank);
  const [search,setSearch] = useState('');
  const [busy,setBusy] = useState(false);
  const [notice,setNotice] = useState('');
  useEffect(() => {api('/workflow/clients').then((r)=>setClients(r.clients||[])).catch((err)=>{setClients([]);setNotice(err.message);});},[]);
  const rows=useMemo(()=>(clients||[]).filter((c)=> !search || [c.name,c.company_name,c.email,c.phone].join(' ').toLowerCase().includes(search.toLowerCase())),[clients,search]);
  function edit(row=null) {setEditing(row||'new');setForm(row?{...blank,...row}:{...blank});setNotice('');}
  async function submit(event) {
    event.preventDefault();setBusy(true);setNotice('');
    try {
      const body={name:form.name,client_type:form.client_type,company_name:form.company_name,contact_person:form.contact_person,email:form.email,phone:form.phone};
      const result=await api(editing==='new'?'/workflow/clients':'/workflow/clients/'+editing.id,{method:editing==='new'?'POST':'PATCH',body});
      setClients((prev)=>editing==='new'?[result.client,...prev]:prev.map((c)=>c.id===result.client.id?result.client:c));
      setEditing(null);setNotice('Client record saved.');
    }catch(err){setNotice(err.message);}finally{setBusy(false);}
  }
  if(!clients) return <PageLoader label="Loading clients…"/>;
  return <div className="workspace-page"><PageIntro eyebrow="Secure client directory" title="Client management"
    text="Manage authorised individual and corporate client profiles for your service work."
    actions={<button className="button dark" onClick={()=>edit()}><Icon name="plus" size={16}/>Add client</button>}/>
    {notice&&<div className="workflow-feedback" role="status">{notice}</div>}
    <div className="filter-panel"><div className="search-field compact"><Icon name="search" size={17}/><input placeholder="Search name, company, email or phone…" value={search} onChange={(e)=>setSearch(e.target.value)} aria-label="Search clients"/></div><span className="result-count">{rows.length} clients</span></div>
    {rows.length?<section className="panel workflow-panel"><div className="table-scroll"><table className="data-table">
      <thead><tr><th>Client</th><th>Type</th><th>Contact</th><th>Added</th><th>Action</th></tr></thead>
      <tbody>{rows.map((row)=><tr key={row.id}><td><strong>{row.name}</strong><small>{row.company_name}</small></td><td>{row.client_type||'individual'}</td><td>{row.email||'—'}<small>{row.phone}</small></td><td>{dateLabel(row.created_at)}</td><td><div className="inline-actions"><button className="button light small" onClick={()=>edit(row)}>Edit</button><Link className="button light small" to="/services">New work</Link></div></td></tr>)}</tbody>
    </table></div></section>:<EmptyState icon="users" title="No clients yet." text="Create a client profile before submitting their service work." action={<button className="button dark" onClick={()=>edit()}>Add client</button>}/>}
    {editing&&<div className="modal-backdrop" onClick={()=>setEditing(null)}><form className="modal-card workflow-modal" onSubmit={submit} onClick={(e)=>e.stopPropagation()}>
      <div className="panel-heading"><h3>{editing==='new'?'New client':'Edit client'}</h3><button type="button" className="icon-link" aria-label="Close" onClick={()=>setEditing(null)}><Icon name="close"/></button></div>
      <label>Type<select disabled={editing!=='new'} value={form.client_type} onChange={(e)=>setForm({...form,client_type:e.target.value})}><option value="individual">Individual</option><option value="corporate">Corporate</option></select></label>
      <label>Client name<input required maxLength={120} value={form.name} onChange={(e)=>setForm({...form,name:e.target.value})}/></label>
      {form.client_type==='corporate'&&<label>Company name<input required maxLength={150} value={form.company_name} onChange={(e)=>setForm({...form,company_name:e.target.value})}/></label>}
      <label>Contact person<input maxLength={120} value={form.contact_person} onChange={(e)=>setForm({...form,contact_person:e.target.value})}/></label>
      <label>Email<input type="email" maxLength={160} value={form.email} onChange={(e)=>setForm({...form,email:e.target.value})}/></label>
      <label>Phone<input type="tel" maxLength={30} value={form.phone} onChange={(e)=>setForm({...form,phone:e.target.value})}/></label>
      <div className="inline-actions"><button type="button" className="button light" onClick={()=>setEditing(null)}>Cancel</button><button className="button dark" disabled={busy}>{busy?'Saving…':'Save client'}</button></div>
    </form></div>}
  </div>;
}
