import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import { IsIn, IsInt, IsOptional, IsString, Length, Max, Min } from 'class-validator';

export class FeedbackListQueryDto {
  @ApiPropertyOptional({ minimum: 1, maximum: 100, default: 25 })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100)
  limit = 25;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  @Length(1, 500)
  cursor?: string;

  @ApiPropertyOptional({ enum: ['open', 'in_progress', 'resolved', 'closed'] })
  @IsOptional()
  @IsIn(['open', 'in_progress', 'resolved', 'closed'])
  status?: 'open' | 'in_progress' | 'resolved' | 'closed';

  @ApiPropertyOptional({ enum: ['bug', 'idea'] })
  @IsOptional()
  @IsIn(['bug', 'idea'])
  kind?: 'bug' | 'idea';
}

export class CreateFeedbackDto {
  @ApiProperty({ enum: ['bug', 'idea'] })
  @IsIn(['bug', 'idea'])
  kind!: 'bug' | 'idea';

  @ApiProperty({ maxLength: 200 })
  @IsString()
  @Length(1, 200)
  title!: string;

  @ApiProperty({ maxLength: 10000 })
  @IsString()
  @Length(1, 10000)
  message!: string;

  @ApiPropertyOptional({ enum: ['low', 'medium', 'high'] })
  @IsOptional()
  @IsIn(['low', 'medium', 'high'])
  severity?: 'low' | 'medium' | 'high';
}

export class UpdateOwnedFeedbackStatusDto {
  @ApiProperty({
    enum: ['open', 'closed'],
    description:
      'Authors may reopen or close their own feedback; staff workflow states are admin-only.',
  })
  @IsIn(['open', 'closed'])
  status!: 'open' | 'closed';
}

export class FeedbackResponseDto {
  @ApiProperty({ type: Object }) data!: object;
}
