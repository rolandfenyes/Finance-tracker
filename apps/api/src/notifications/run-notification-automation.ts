import { NestFactory } from '@nestjs/core';
import { AppModule } from '../app.module';
import {
  NOTIFICATION_AUTOMATION_KINDS,
  NotificationAutomationService,
  type NotificationAutomationKind,
} from './notification-automation.service';

async function main(): Promise<void> {
  const kind = process.argv[2];
  if (!isKind(kind)) {
    throw new Error(
      `Notification workflow must be one of: ${NOTIFICATION_AUTOMATION_KINDS.join(', ')}`,
    );
  }
  const referenceDate = process.argv[3] ?? new Date().toISOString().slice(0, 10);
  const application = await NestFactory.createApplicationContext(AppModule, {
    logger: ['error', 'warn', 'log'],
  });
  try {
    await application.get(NotificationAutomationService).run(kind, referenceDate);
  } finally {
    await application.close();
  }
}

function isKind(value: string | undefined): value is NotificationAutomationKind {
  return NOTIFICATION_AUTOMATION_KINDS.some((kind) => kind === value);
}

void main().catch((error: unknown) => {
  const message = error instanceof Error ? error.message : 'Notification workflow failed';
  process.stderr.write(`${message}\n`);
  process.exitCode = 1;
});
