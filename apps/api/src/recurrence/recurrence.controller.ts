import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  Inject,
  Param,
  ParseUUIDPipe,
  Patch,
  Post,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBody,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnprocessableEntityResponse,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  CreateRecurringRuleDto,
  RecurringRulesQueryDto,
  RecurringRulesResponseDto,
  UpdateRecurringRuleDto,
} from './recurrence.dto';
import { RecurrenceService } from './recurrence.service';

@ApiTags('Recurrence')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('recurring-rules')
export class RecurrenceController {
  constructor(@Inject(RecurrenceService) private readonly recurrence: RecurrenceService) {}

  @Get()
  @ApiOperation({
    summary: 'List owned recurring rules with an optional side-effect-free forecast range',
  })
  @ApiOkResponse({ type: RecurringRulesResponseDto })
  rules(
    @Req() request: Request,
    @Query() query: RecurringRulesQueryDto,
  ): Promise<RecurringRulesResponseDto> {
    return this.recurrence.rules(request.session.principal!.userId, query);
  }

  @Post()
  @ApiOperation({ summary: 'Create a forecast recurrence with an explicit economic type' })
  @ApiBody({
    type: CreateRecurringRuleDto,
    examples: {
      monthlyExpense: {
        value: {
          title: 'Rent',
          amount: '125.50',
          currency: 'HUF',
          economicType: 'expense',
          startsOn: '2026-07-31',
          rrule: 'FREQ=MONTHLY;BYMONTHDAY=31',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: RecurringRulesResponseDto })
  @ApiUnprocessableEntityResponse({
    description: 'RRULE, amount, currency, category, or economic-type invariant failed',
  })
  create(
    @Body() dto: CreateRecurringRuleDto,
    @Req() request: Request,
  ): Promise<RecurringRulesResponseDto> {
    const principal = request.session.principal!;
    return this.recurrence.create(principal.userId, principal.role, dto);
  }

  @Patch(':id')
  @ApiOperation({ summary: 'Update an owned recurring rule and invalidate stored forecasts' })
  @ApiOkResponse({ type: RecurringRulesResponseDto })
  @ApiNotFoundResponse({ description: 'The owned recurring rule was not found' })
  update(
    @Param('id', new ParseUUIDPipe({ version: '4' })) ruleId: string,
    @Body() dto: UpdateRecurringRuleDto,
    @Req() request: Request,
  ): Promise<RecurringRulesResponseDto> {
    return this.recurrence.update(request.session.principal!.userId, ruleId, dto);
  }

  @Delete(':id')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete an owned recurring rule and its forecast occurrences' })
  @ApiNoContentResponse()
  async delete(
    @Param('id', new ParseUUIDPipe({ version: '4' })) ruleId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.recurrence.delete(request.session.principal!.userId, ruleId);
  }
}
