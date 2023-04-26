import { JsonDecoder } from 'ts.data.json';

import { ListingMeta, Listing } from './models';

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
}

const buildListingDecoder = <TEntity>({
  entityDecoder,
  entityDecoderName,
  listingDecoderName
}: ListingDecoderOptions<TEntity>): JsonDecoder.Decoder<Listing<TEntity>> =>
  JsonDecoder.object<Listing<TEntity>>(
    {
      meta: metaDecoder,
      result: JsonDecoder.array(entityDecoder, entityDecoderName)
    },
    listingDecoderName
  );

export default buildListingDecoder;
