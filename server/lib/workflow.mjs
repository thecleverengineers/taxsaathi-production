import { isoNow, statusLabel } from './legacy.mjs';

function stageFor(order) {
  const payment = String(order.payment_status || '').toLowerCase();
  const status = String(order.status || order.order_status || 'submitted').toLowerCase();
  if (!['paid', 'verified', 'approved'].includes(payment)) return 'payment_pending';
  if (status === 'submitted') return 'approval_pending';
  if (status === 'approved' && !order.assigned_user_id) return 'assignment_pending';
  if (status === 'approved' || status === 'work_in_progress') return 'executive_work_pending';
  return 'completion_pending';
}

export async function runWorkflowReminders(store, { now = new Date() } = {}) {
  const today = now.toISOString().slice(0, 10);
  const slot = now.getUTCHours() < 12 ? 'morning' : 'evening';
  const orders = await store.find('orders', {}, { sort: { id: 1 } });
  const users = await store.find('users', { is_active: 1 }, { sort: { id: 1 } });
  const roles = await store.find('roles', {});
  const roleById = new Map(roles.map((role) => [Number(role.id), role]));
  let created = 0;

  for (const order of orders) {
    const terminal = ['completed', 'rejected', 'cancelled'].includes(String(order.status || '').toLowerCase());
    if (terminal) continue;
    const stage = stageFor(order);
    const recipients = users.filter((user) => {
      const role = roleById.get(Number(user.role_id));
      const slug = String(role?.slug || '').toLowerCase();
      if (['admin', 'manager'].includes(slug)) return true;
      if (slug === 'executive') return Number(order.assigned_user_id) === Number(user.id);
      if (slug === 'client') return Number(order.client_id) === Number(user.client_id);
      return false;
    });

    for (const user of recipients) {
      const exists = await store.findOne('order_workflow_reminder_logs', {
        order_id: Number(order.id),
        user_id: Number(user.id),
        reminder_date: today,
        reminder_slot: slot,
        workflow_stage: stage
      });
      if (exists) continue;
      await store.insert('order_workflow_reminder_logs', {
        order_id: Number(order.id),
        user_id: Number(user.id),
        reminder_date: today,
        reminder_slot: slot,
        workflow_stage: stage,
        created_at: isoNow()
      });
      await store.insert('notifications', {
        uid: `workflow-${order.id}-${user.id}-${today}-${slot}`,
        user_id: Number(user.id),
        title: 'Workflow reminder',
        message: `${order.order_no || `Order #${order.id}`} is waiting at ${statusLabel(stage)}.`,
        body: `${order.order_no || `Order #${order.id}`} is waiting at ${statusLabel(stage)}.`,
        kind: 'workflow_reminder',
        severity: stage === 'payment_pending' ? 'warning' : 'info',
        status_label: statusLabel(stage),
        url: `/orders/${order.id}`,
        data_json: JSON.stringify({ order_id: order.id, workflow_stage: stage }),
        payload: JSON.stringify({ order_id: order.id, workflow_stage: stage }),
        is_read: 0,
        created_at: isoNow(),
        updated_at: isoNow()
      });
      created += 1;
    }
  }

  return { ok: true, created, date: today, slot };
}

export { stageFor };
