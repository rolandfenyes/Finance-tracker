import { performance } from 'node:perf_hooks';
import { PasswordService } from './password.service';

async function main(): Promise<void> {
  const service = new PasswordService();
  const samples: number[] = [];
  for (let index = 0; index < 5; index += 1) {
    const started = performance.now();
    await service.hash(`synthetic-benchmark-password-${index}`);
    samples.push(performance.now() - started);
  }
  samples.sort((left, right) => left - right);
  const median = samples[Math.floor(samples.length / 2)]!;
  console.info(
    JSON.stringify({
      algorithm: 'argon2id',
      memoryKiB: 19 * 1024,
      iterations: 2,
      parallelism: 1,
      samplesMilliseconds: samples.map((value) => Math.round(value)),
      medianMilliseconds: Math.round(median),
    }),
  );
}

void main();
