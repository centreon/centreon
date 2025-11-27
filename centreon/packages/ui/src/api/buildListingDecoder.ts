import { equals } from 'ramda';
import { JsonDecoder } from 'ts.data.json';
import { Listing, ListingMeta } from './models';

const metaDecoder = JsonDecoder.object<ListingMeta>(
  {
    limit: JsonDecoder.number,
    page: JsonDecoder.number,
    total: JsonDecoder.number
  },
  'ListingMeta'
);

interface ListingDecoderOptions<TEntity> {
  entityDecoder: JsonDecoder.Decoder<TEntity>;
  entityDecoderName: string;
  listingDecoderName: string;
  apiFormat?: 'Standard' | 'JSON-LD';
}

const jsonLdListingDecoder = <TEntity>(
  entityDecoder: JsonDecoder.Decoder<TEntity>,
  entityDecoderName: string,
  listingDecoderName: string
): JsonDecoder.Decoder<Listing<TEntity>> =>
  JsonDecoder.object(
    {
      member: JsonDecoder.array(entityDecoder, entityDecoderName),
      totalItems: JsonDecoder.number
    },
    listingDecoderName
  ).map((data) => ({
    result: data.member,
    meta: {
      total: data.totalItems,
      page: 1,
      limit: data.member.length
    }
  })) as JsonDecoder.Decoder<Listing<TEntity>>;

const standardListingDecoder = <TEntity>(
  entityDecoder: JsonDecoder.Decoder<TEntity>,
  entityDecoderName: string,
  listingDecoderName: string
): JsonDecoder.Decoder<Listing<TEntity>> =>
  JsonDecoder.object<Listing<TEntity>>(
    {
      meta: metaDecoder,
      result: JsonDecoder.array(entityDecoder, entityDecoderName)
    },
    listingDecoderName
  );

const buildListingDecoder = <TEntity>({
  entityDecoder,
  entityDecoderName,
  listingDecoderName,
  apiFormat = 'Standard'
}: ListingDecoderOptions<TEntity>): JsonDecoder.Decoder<Listing<TEntity>> => {
  return (
    equals(apiFormat, 'JSON-LD') ? jsonLdListingDecoder : standardListingDecoder
  )(entityDecoder, entityDecoderName, listingDecoderName);
};

export default buildListingDecoder;
