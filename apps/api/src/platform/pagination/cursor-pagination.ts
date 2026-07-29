export class PageCursor {
  private constructor(private readonly value: string) {}

  static create(value: string): PageCursor {
    if (value.trim() === '') {
      throw new Error('Page cursor must not be empty');
    }
    return new PageCursor(value);
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }
}

export class PaginationLimit {
  private constructor(readonly value: number) {}

  static create(value: number, maximum: number): PaginationLimit {
    if (!Number.isSafeInteger(maximum) || maximum < 1) {
      throw new Error('Pagination maximum must be a positive safe integer');
    }
    if (!Number.isSafeInteger(value) || value < 1 || value > maximum) {
      throw new Error(`Pagination limit must be an integer between 1 and ${maximum}`);
    }
    return new PaginationLimit(value);
  }
}

export interface CursorPage<T> {
  items: T[];
  nextCursor: string | null;
}
