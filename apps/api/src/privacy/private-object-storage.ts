import {
  GetObjectCommand,
  PutObjectCommand,
  DeleteObjectCommand,
  S3Client,
} from '@aws-sdk/client-s3';
import { getSignedUrl } from '@aws-sdk/s3-request-presigner';
import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

export const PRIVATE_OBJECT_STORAGE = Symbol('PRIVATE_OBJECT_STORAGE');

export interface PrivateObjectStorage {
  put(input: { key: string; body: Uint8Array; mediaType: string; expiresAt: Date }): Promise<void>;
  delete(key: string): Promise<void>;
  signedGetUrl(key: string, expiresInSeconds: number): Promise<string>;
}

@Injectable()
export class S3PrivateObjectStorage implements PrivateObjectStorage {
  private readonly client: S3Client;
  private readonly bucket: string;

  constructor(@Inject(ConfigService) config: ConfigService) {
    this.bucket = config.getOrThrow<string>('PRIVACY_EXPORT_S3_BUCKET');
    this.client = new S3Client({
      region: config.getOrThrow<string>('PRIVACY_EXPORT_S3_REGION'),
    });
  }

  async put(input: {
    key: string;
    body: Uint8Array;
    mediaType: string;
    expiresAt: Date;
  }): Promise<void> {
    await this.client.send(
      new PutObjectCommand({
        Bucket: this.bucket,
        Key: input.key,
        Body: input.body,
        ContentType: input.mediaType,
        CacheControl: 'private, no-store',
        Expires: input.expiresAt,
        ServerSideEncryption: 'AES256',
        Metadata: { expires_at: input.expiresAt.toISOString() },
      }),
    );
  }

  async delete(key: string): Promise<void> {
    await this.client.send(new DeleteObjectCommand({ Bucket: this.bucket, Key: key }));
  }

  signedGetUrl(key: string, expiresInSeconds: number): Promise<string> {
    return getSignedUrl(
      this.client,
      new GetObjectCommand({
        Bucket: this.bucket,
        Key: key,
        ResponseCacheControl: 'private, no-store',
      }),
      { expiresIn: expiresInSeconds },
    );
  }
}

export class DisabledPrivateObjectStorage implements PrivateObjectStorage {
  put(): Promise<void> {
    return Promise.reject(new Error('privacy_object_storage_disabled'));
  }

  delete(): Promise<void> {
    return Promise.resolve();
  }

  signedGetUrl(): Promise<string> {
    return Promise.reject(new Error('privacy_object_storage_disabled'));
  }
}
