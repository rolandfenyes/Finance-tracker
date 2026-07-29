import { Module } from '@nestjs/common';
import { CLOCK, SystemClock } from './clock';

@Module({
  providers: [{ provide: CLOCK, useFactory: (): SystemClock => new SystemClock() }],
  exports: [CLOCK],
})
export class TimeModule {}
