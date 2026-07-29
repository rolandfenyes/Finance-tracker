import Decimal from 'decimal.js';

export const roundingModes = ['DOWN', 'UP', 'HALF_UP', 'HALF_EVEN'] as const;
export type RoundingMode = (typeof roundingModes)[number];

const decimalModes: Record<RoundingMode, Decimal.Rounding> = {
  DOWN: Decimal.ROUND_DOWN,
  UP: Decimal.ROUND_UP,
  HALF_UP: Decimal.ROUND_HALF_UP,
  HALF_EVEN: Decimal.ROUND_HALF_EVEN,
};

export class RoundingPolicy {
  readonly decimalMode: Decimal.Rounding;

  private constructor(
    readonly scale: number,
    readonly mode: RoundingMode,
  ) {
    this.decimalMode = decimalModes[mode];
  }

  static create(scale: number, mode: RoundingMode): RoundingPolicy {
    if (!Number.isSafeInteger(scale) || scale < 0 || scale > 100) {
      throw new Error('Rounding scale must be an integer between 0 and 100');
    }
    if (!roundingModes.includes(mode)) {
      throw new Error('Unsupported rounding mode');
    }
    return new RoundingPolicy(scale, mode);
  }
}
