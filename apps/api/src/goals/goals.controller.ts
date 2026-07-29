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
  CreateGoalContributionDto,
  CreateGoalDto,
  CreateGoalRecurringRuleDto,
  GoalResponseDto,
  GoalsResponseDto,
  ReverseGoalContributionDto,
  UpdateGoalDto,
} from './goals.dto';
import { GoalsService } from './goals.service';

@ApiTags('Goals')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('goals')
export class GoalsController {
  constructor(@Inject(GoalsService) private readonly goals: GoalsService) {}

  @Get()
  @ApiOperation({
    summary: 'List owned goals with ledger-derived progress, contribution history, and schedule',
  })
  @ApiOkResponse({ type: GoalsResponseDto })
  list(@Req() request: Request): Promise<GoalsResponseDto> {
    return this.goals.goals(request.session.principal!.userId);
  }

  @Post()
  @ApiOperation({ summary: 'Create an owned zero-balance goal and its ledger bucket' })
  @ApiBody({
    type: CreateGoalDto,
    examples: {
      savingsGoal: {
        value: {
          title: 'Emergency laptop',
          targetAmount: '1000.00',
          currency: 'HUF',
          deadline: '2026-12-31',
          priority: 3,
          status: 'active',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: GoalsResponseDto })
  @ApiUnprocessableEntityResponse({ description: 'Goal financial or ownership invariant failed' })
  create(@Body() dto: CreateGoalDto, @Req() request: Request): Promise<GoalsResponseDto> {
    const principal = request.session.principal!;
    return this.goals.create(principal.userId, principal.role, dto);
  }

  @Patch(':id')
  @ApiOperation({
    summary: 'Update an open goal without reducing its target below derived progress',
  })
  @ApiOkResponse({ type: GoalsResponseDto })
  @ApiNotFoundResponse()
  @ApiConflictResponse({ description: 'Archived goal or immutable currency history' })
  update(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Body() dto: UpdateGoalDto,
    @Req() request: Request,
  ): Promise<GoalsResponseDto> {
    return this.goals.update(request.session.principal!.userId, goalId, dto);
  }

  @Post(':id/archive')
  @ApiOperation({ summary: 'Archive a goal without posting income or changing its balance' })
  @ApiOkResponse({ type: GoalsResponseDto })
  archive(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Req() request: Request,
  ): Promise<GoalsResponseDto> {
    return this.goals.archive(request.session.principal!.userId, goalId);
  }

  @Post(':id/unarchive')
  @ApiOperation({
    summary: 'Restore goal visibility without posting or deleting financial history',
  })
  @ApiOkResponse({ type: GoalsResponseDto })
  unarchive(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Req() request: Request,
  ): Promise<GoalsResponseDto> {
    const principal = request.session.principal!;
    return this.goals.unarchive(principal.userId, principal.role, goalId);
  }

  @Delete(':id')
  @HttpCode(204)
  @ApiOperation({
    summary: 'Delete an empty goal; goals with financial history must be archived',
  })
  @ApiNoContentResponse()
  @ApiConflictResponse({ description: 'The goal has immutable contribution history' })
  async delete(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.goals.delete(request.session.principal!.userId, goalId);
  }

  @Post(':id/contributions')
  @ApiOperation({
    summary: 'Post an idempotent internal transfer into a goal without overfunding',
  })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiBody({
    type: CreateGoalContributionDto,
    examples: {
      exactContribution: {
        value: {
          amount: '400.00',
          currency: 'HUF',
          occurredOn: '2026-07-29',
          note: 'Synthetic contribution',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: GoalResponseDto })
  @ApiConflictResponse({ description: 'The goal is completed or archived' })
  @ApiUnprocessableEntityResponse({ description: 'FX or overfunding policy failed' })
  async contribute(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Body() dto: CreateGoalContributionDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<GoalResponseDto> {
    const result = await this.goals.contribution(
      request.session.principal!.userId,
      goalId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':goalId/contributions/:id/corrections')
  @ApiOperation({ summary: 'Reverse and replace an owned goal contribution atomically' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: GoalResponseDto })
  @ApiNotFoundResponse()
  @ApiConflictResponse({ description: 'The contribution was already corrected or reversed' })
  async correct(
    @Param('goalId', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Param('id', new ParseUUIDPipe({ version: '4' })) contributionId: string,
    @Body() dto: CreateGoalContributionDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<GoalResponseDto> {
    const result = await this.goals.correctContribution(
      request.session.principal!.userId,
      goalId,
      contributionId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':goalId/contributions/:id/reversals')
  @ApiOperation({ summary: 'Reverse an owned goal contribution and reconcile derived progress' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: GoalResponseDto })
  @ApiNotFoundResponse()
  @ApiConflictResponse({ description: 'The contribution was already corrected or reversed' })
  async reverse(
    @Param('goalId', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Param('id', new ParseUUIDPipe({ version: '4' })) contributionId: string,
    @Body() dto: ReverseGoalContributionDto,
    @Headers('idempotency-key') key: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<GoalResponseDto> {
    const result = await this.goals.reverseContribution(
      request.session.principal!.userId,
      goalId,
      contributionId,
      key,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':id/recurring-rule')
  @ApiOperation({ summary: 'Attach one transfer forecast to an open goal' })
  @ApiCreatedResponse({ type: GoalsResponseDto })
  @ApiConflictResponse({ description: 'The goal is locked or already has a schedule' })
  createRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Body() dto: CreateGoalRecurringRuleDto,
    @Req() request: Request,
  ): Promise<GoalsResponseDto> {
    const principal = request.session.principal!;
    return this.goals.createRule(principal.userId, principal.role, goalId, dto);
  }

  @Put(':id/recurring-rule')
  @ApiOperation({ summary: 'Replace the attached goal transfer forecast' })
  @ApiOkResponse({ type: GoalsResponseDto })
  @ApiNotFoundResponse()
  updateRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Body() dto: CreateGoalRecurringRuleDto,
    @Req() request: Request,
  ): Promise<GoalsResponseDto> {
    return this.goals.updateRule(request.session.principal!.userId, goalId, dto);
  }

  @Delete(':id/recurring-rule')
  @HttpCode(204)
  @ApiOperation({ summary: 'Remove the attached goal forecast without changing goal progress' })
  @ApiNoContentResponse()
  async deleteRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) goalId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.goals.deleteRule(request.session.principal!.userId, goalId);
  }
}
