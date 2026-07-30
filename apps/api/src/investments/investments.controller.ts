import {
  Body,
  Controller,
  Delete,
  Get,
  Headers,
  HttpCode,
  Inject,
  Param,
  ParseUUIDPipe,
  Patch,
  Post,
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
  ApiNoContentResponse,
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
  CreateInvestmentDto,
  CreateInvestmentMovementDto,
  CreateInvestmentRecurringRuleDto,
  InvestmentResponseDto,
  InvestmentsResponseDto,
  ReverseInvestmentMovementDto,
  UpdateInvestmentDto,
} from './investments.dto';
import { InvestmentsService } from './investments.service';

@ApiTags('Investments')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('investments')
export class InvestmentsController {
  constructor(@Inject(InvestmentsService) private readonly investments: InvestmentsService) {}

  @Get()
  @ApiOperation({
    summary: 'List owned generic investments with ledger-derived balances and labeled scenarios',
  })
  @ApiOkResponse({ type: InvestmentsResponseDto })
  list(@Req() request: Request): Promise<InvestmentsResponseDto> {
    return this.investments.investments(request.session.principal!.userId);
  }

  @Post()
  @ApiOperation({ summary: 'Create a generic investment and its owned ledger account' })
  @ApiBody({
    type: CreateInvestmentDto,
    examples: {
      savings: {
        value: {
          type: 'savings',
          name: 'Synthetic reserve account',
          provider: 'Synthetic provider',
          currency: 'HUF',
          scenarioAnnualRate: '5',
          scenarioFrequency: 'monthly',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: InvestmentsResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'Currency, rate, or ownership invariant failed' })
  create(
    @Body() dto: CreateInvestmentDto,
    @Req() request: Request,
  ): Promise<InvestmentsResponseDto> {
    const principal = request.session.principal!;
    return this.investments.create(principal.userId, principal.role, dto);
  }

  @Patch(':id')
  @ApiOperation({
    summary: 'Update investment metadata or its user-authored scenario without changing balance',
  })
  @ApiOkResponse({ type: InvestmentsResponseDto })
  @ApiNotFoundResponse()
  update(
    @Param('id', new ParseUUIDPipe({ version: '4' })) investmentId: string,
    @Body() dto: UpdateInvestmentDto,
    @Req() request: Request,
  ): Promise<InvestmentsResponseDto> {
    return this.investments.update(request.session.principal!.userId, investmentId, dto);
  }

  @Delete(':id')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete a history-free investment and its recurring forecast' })
  @ApiNoContentResponse()
  @ApiConflictResponse({ description: 'Immutable history or emergency-reserve linkage exists' })
  async delete(
    @Param('id', new ParseUUIDPipe({ version: '4' })) investmentId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.investments.delete(request.session.principal!.userId, investmentId);
  }

  @Post(':id/movements')
  @ApiOperation({ summary: 'Post an idempotent deposit or withdrawal as an internal transfer' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: InvestmentResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'FX or available-balance policy failed' })
  async movement(
    @Param('id', new ParseUUIDPipe({ version: '4' })) investmentId: string,
    @Body() dto: CreateInvestmentMovementDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<InvestmentResponseDto> {
    const result = await this.investments.movement(
      request.session.principal!.userId,
      investmentId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':investmentId/movements/:id/reversals')
  @ApiOperation({ summary: 'Reverse an owned investment transfer without deleting history' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: InvestmentResponseDto })
  async reverseMovement(
    @Param('investmentId', new ParseUUIDPipe({ version: '4' })) investmentId: string,
    @Param('id', new ParseUUIDPipe({ version: '4' })) movementId: string,
    @Body() dto: ReverseInvestmentMovementDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<InvestmentResponseDto> {
    const result = await this.investments.reverseMovement(
      request.session.principal!.userId,
      investmentId,
      movementId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':id/recurring-rule')
  @ApiOperation({
    summary: 'Create a transfer forecast for recurring investment contributions',
  })
  @ApiCreatedResponse({ type: InvestmentsResponseDto })
  @ApiConflictResponse({ description: 'The investment already has a recurring rule' })
  createRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) investmentId: string,
    @Body() dto: CreateInvestmentRecurringRuleDto,
    @Req() request: Request,
  ): Promise<InvestmentsResponseDto> {
    const principal = request.session.principal!;
    return this.investments.createRule(principal.userId, principal.role, investmentId, dto);
  }
}
