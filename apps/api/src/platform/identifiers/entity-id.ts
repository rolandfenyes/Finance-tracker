import { randomUUID } from 'node:crypto';

const UUID_V4_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

export class EntityId<Entity extends string = string> {
  private constructor(private readonly value: string) {}

  static create<Entity extends string = string>(value: string): EntityId<Entity> {
    const canonical = value.toLowerCase();
    if (!UUID_V4_PATTERN.test(canonical)) {
      throw new Error('Identifier must be a canonical UUID v4');
    }
    return new EntityId<Entity>(canonical);
  }

  static generate<Entity extends string = string>(): EntityId<Entity> {
    return EntityId.create<Entity>(randomUUID());
  }

  equals(other: EntityId<Entity>): boolean {
    return this.value === other.value;
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }
}
