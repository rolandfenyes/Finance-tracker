import {
  Body,
  Controller,
  Get,
  Headers,
  Inject,
  Param,
  ParseUUIDPipe,
  Post,
  Put,
  Req,
  Res,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBody,
  ApiConflictResponse,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiHeader,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnprocessableEntityResponse,
} from '@nestjs/swagger';
import type { Request, Response } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  CreateEmergencyReserveMovementDto,
  EmergencyReserveResponseDto,
  ReverseEmergencyReserveMovementDto,
  UpdateEmergencyReserveTargetDto,
} from './emergency-reserve.dto';
import { EmergencyReserveService } from './emergency-reserve.service';

@ApiTags('Emergency reserve')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('emergency-reserve')
export class EmergencyReserveController {
  constructor(@Inject(EmergencyReserveService) private readonly reserve: EmergencyReserveService) {}

  @Get()
  @ApiOperation({
    summary: 'Read the owned reserve, ledger-derived allocation, history, and raw schedule context',
  })
  @ApiOkResponse({ type: EmergencyReserveResponseDto })
  read(@Req() request: Request): Promise<EmergencyReserveResponseDto> {
    return this.reserve.reserve(request.session.principal!.userId);
  }

  @Put('target')
  @ApiOperation({
    summary: 'Set the manual reserve target and optional owned investment-account linkage',
  })
  @ApiBody({
    type: UpdateEmergencyReserveTargetDto,
    examples: {
      manualTarget: {
        value: {
          targetAmount: '250000.00',
          currency: 'HUF',
          linkedInvestmentAccountId: null,
        },
      },
    },
  })
  @ApiOkResponse({ type: EmergencyReserveResponseDto })
  @ApiNotFoundResponse({ description: 'Linked investment account was not found' })
  @ApiConflictResponse({ description: 'Currency or holding link is immutable after history' })
  @ApiUnprocessableEntityResponse({ description: 'Target or ownership invariant failed' })
  updateTarget(
    @Body() dto: UpdateEmergencyReserveTargetDto,
    @Req() request: Request,
  ): Promise<EmergencyReserveResponseDto> {
    return this.reserve.updateTarget(request.session.principal!.userId, dto);
  }

  @Post('contributions')
  @ApiOperation({
    summary: 'Post one idempotent internal transfer into the emergency reserve allocation',
  })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: EmergencyReserveResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'FX, currency, or amount invariant failed' })
  contribution(
    @Body() dto: CreateEmergencyReserveMovementDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<EmergencyReserveResponseDto> {
    return this.withReplay(
      this.reserve.contribution(request.session.principal!.userId, key, dto),
      response,
    );
  }

  @Post('withdrawals')
  @ApiOperation({
    summary: 'Post one idempotent internal transfer out of the emergency reserve allocation',
  })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: EmergencyReserveResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'Withdrawal exceeds the derived allocation' })
  withdrawal(
    @Body() dto: CreateEmergencyReserveMovementDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<EmergencyReserveResponseDto> {
    return this.withReplay(
      this.reserve.withdrawal(request.session.principal!.userId, key, dto),
      response,
    );
  }

  @Post('movements/:id/reversals')
  @ApiOperation({
    summary: 'Reverse an owned reserve movement without deleting immutable financial history',
  })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: EmergencyReserveResponseDto })
  @ApiNotFoundResponse()
  @ApiConflictResponse({
    description: 'Already reversed or reversal would make allocation negative',
  })
  reverse(
    @Param('id', new ParseUUIDPipe({ version: '4' })) movementId: string,
    @Body() dto: ReverseEmergencyReserveMovementDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<EmergencyReserveResponseDto> {
    return this.withReplay(
      this.reserve.reverse(request.session.principal!.userId, movementId, key, dto),
      response,
    );
  }

  private async withReplay(
    operation: Promise<{ value: EmergencyReserveResponseDto; replayed: boolean }>,
    response: Response,
  ): Promise<EmergencyReserveResponseDto> {
    const result = await operation;
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }
}
