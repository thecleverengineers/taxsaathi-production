import path from 'node:path';
import dotenv from 'dotenv';
import { DataStore } from '../lib/store.mjs';
import { runWorkflowReminders } from '../lib/workflow.mjs';

dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const store = await new DataStore().connect();
try {
  const result = await runWorkflowReminders(store);
  console.log(JSON.stringify(result));
} finally {
  await store.close();
}
