import { Injectable } from '@nestjs/common';
import type * as Argon2 from 'argon2';

const ARGON2_MEMORY_KIB = 19 * 1024;

@Injectable()
export class PasswordService {
  async hash(password: string): Promise<string> {
    const argon2 = await loadArgon2();
    return argon2.hash(password, {
      type: argon2.argon2id,
      memoryCost: ARGON2_MEMORY_KIB,
      timeCost: 2,
      parallelism: 1,
    });
  }

  async verify(hash: string, password: string): Promise<boolean> {
    try {
      const argon2 = await loadArgon2();
      return await argon2.verify(hash, password);
    } catch {
      return false;
    }
  }

  async needsRehash(hash: string): Promise<boolean> {
    const argon2 = await loadArgon2();
    return argon2.needsRehash(hash, {
      memoryCost: ARGON2_MEMORY_KIB,
      timeCost: 2,
      parallelism: 1,
    });
  }
}

async function loadArgon2(): Promise<typeof Argon2> {
  return import('argon2');
}
