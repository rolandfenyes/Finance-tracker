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
  CreateLoanDto,
  CreateLoanPaymentDto,
  CreateLoanRecurringRuleDto,
  LoanResponseDto,
  LoansResponseDto,
  ReverseLoanPaymentDto,
  UpdateLoanDto,
} from './loans.dto';
import { LoansService } from './loans.service';

@ApiTags('Loans')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('loans')
export class LoansController {
  constructor(@Inject(LoansService) private readonly loans: LoansService) {}

  @Get()
  @ApiOperation({
    summary: 'List owned loans with separate projected schedule and posted payment history',
  })
  @ApiOkResponse({ type: LoansResponseDto })
  list(@Req() request: Request): Promise<LoansResponseDto> {
    return this.loans.loans(request.session.principal!.userId);
  }

  @Post()
  @ApiOperation({ summary: 'Create an owned loan and its liability ledger account' })
  @ApiBody({
    type: CreateLoanDto,
    examples: {
      fixedNominal: {
        value: {
          title: 'Synthetic fixed-rate loan',
          principal: '120000',
          currency: 'HUF',
          nominalAnnualRate: '12',
          termMonths: 12,
          startsOn: '2026-07-30',
          paymentDay: 30,
          extraPaymentScenario: '0',
          insuranceMonthly: '500',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: LoansResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'Loan financial or ownership invariant failed' })
  create(@Body() dto: CreateLoanDto, @Req() request: Request): Promise<LoansResponseDto> {
    const principal = request.session.principal!;
    return this.loans.create(principal.userId, principal.role, dto);
  }

  @Patch(':id')
  @ApiOperation({ summary: 'Update open loan configuration without rewriting posted history' })
  @ApiOkResponse({ type: LoansResponseDto })
  @ApiNotFoundResponse()
  @ApiConflictResponse({ description: 'The loan is archived' })
  update(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Body() dto: UpdateLoanDto,
    @Req() request: Request,
  ): Promise<LoansResponseDto> {
    return this.loans.update(request.session.principal!.userId, loanId, dto);
  }

  @Post(':id/archive')
  @HttpCode(200)
  @ApiOperation({ summary: 'Archive a fully repaid loan without deleting payment history' })
  @ApiOkResponse({ type: LoansResponseDto })
  @ApiConflictResponse({ description: 'The loan still has outstanding principal' })
  archive(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Req() request: Request,
  ): Promise<LoansResponseDto> {
    return this.loans.archive(request.session.principal!.userId, loanId);
  }

  @Delete(':id')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete a history-free loan; otherwise archive it' })
  @ApiNoContentResponse()
  @ApiConflictResponse({ description: 'The loan has immutable payment history' })
  async delete(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.loans.delete(request.session.principal!.userId, loanId);
  }

  @Post(':id/payments')
  @ApiOperation({ summary: 'Post an idempotent repayment with explicit financial components' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: LoanResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'FX, component, or overpayment policy failed' })
  async payment(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Body() dto: CreateLoanPaymentDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<LoanResponseDto> {
    const result = await this.loans.payment(request.session.principal!.userId, loanId, key, dto);
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':loanId/payments/:id/corrections')
  @ApiOperation({ summary: 'Reverse and replace an owned loan payment atomically' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: LoanResponseDto })
  async correct(
    @Param('loanId', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Param('id', new ParseUUIDPipe({ version: '4' })) paymentId: string,
    @Body() dto: CreateLoanPaymentDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<LoanResponseDto> {
    const result = await this.loans.correctPayment(
      request.session.principal!.userId,
      loanId,
      paymentId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':loanId/payments/:id/reversals')
  @ApiOperation({ summary: 'Reverse an owned repayment and reopen a paid-off loan' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: LoanResponseDto })
  async reverse(
    @Param('loanId', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Param('id', new ParseUUIDPipe({ version: '4' })) paymentId: string,
    @Body() dto: ReverseLoanPaymentDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<LoanResponseDto> {
    const result = await this.loans.reversePayment(
      request.session.principal!.userId,
      loanId,
      paymentId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':id/recurring-rule')
  @ApiOperation({ summary: 'Create the loan repayment schedule used by the recurrence worker' })
  @ApiCreatedResponse({ type: LoansResponseDto })
  createRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Body() dto: CreateLoanRecurringRuleDto,
    @Req() request: Request,
  ): Promise<LoansResponseDto> {
    const principal = request.session.principal!;
    return this.loans.createRule(principal.userId, principal.role, loanId, dto);
  }

  @Put(':id/recurring-rule')
  @ApiOperation({ summary: 'Replace an owned loan repayment schedule' })
  @ApiOkResponse({ type: LoansResponseDto })
  updateRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Body() dto: CreateLoanRecurringRuleDto,
    @Req() request: Request,
  ): Promise<LoansResponseDto> {
    return this.loans.updateRule(request.session.principal!.userId, loanId, dto);
  }

  @Delete(':id/recurring-rule')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete an owned loan repayment schedule and its forecasts' })
  @ApiNoContentResponse()
  async deleteRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) loanId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.loans.deleteRule(request.session.principal!.userId, loanId);
  }
}
