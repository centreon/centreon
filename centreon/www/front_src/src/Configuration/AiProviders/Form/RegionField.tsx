import { Autocomplete, TextField, Typography } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { labelAwsRegion, labelAwsRegionPlaceholder } from '../translatedLabels';

// Bedrock is reached through its OpenAI-compatible endpoint, whose host carries
// the AWS region. The form only exposes the region; the endpoint URL is derived
// from it (and the region parsed back from it when editing).
const bedrockEndpoint = (region: string): string =>
  `https://bedrock-mantle.${region}.api.aws`;

const regionFromEndpoint = (endpointUrl: string): string =>
  endpointUrl.match(/bedrock-mantle\.([^.]+)\.api\.aws/)?.[1] ?? '';

// Bedrock cross-region inference profiles are geography-scoped (us/eu/apac,
// plus us-gov for GovCloud); regions without a geography (Canada, South
// America, …) route through the global profile, so the model id prefix must
// match the region accordingly
const inferenceProfilePrefix = (region: string): string => {
  if (region.startsWith('us-gov-')) {
    return 'us-gov.';
  }
  if (region.startsWith('us-')) {
    return 'us.';
  }
  if (region.startsWith('eu-')) {
    return 'eu.';
  }
  if (region.startsWith('ap-')) {
    return 'apac.';
  }

  return 'global.';
};

// re-prefix a model id that already uses an inference profile, leaving plain
// model ids (e.g. openai.gpt-oss-120b) and empty values untouched
const profilePrefixPattern = /^(us-gov|eu|us|apac|global)\./;

const realignModelToRegion = (model: string, region: string): string =>
  profilePrefixPattern.test(model)
    ? model.replace(profilePrefixPattern, inferenceProfilePrefix(region))
    : model;

// a short list of common AWS regions; any region can still be typed in
const awsRegions: Record<string, string> = {
  'ap-northeast-1': 'Asia Pacific (Tokyo)',
  'ap-south-1': 'Asia Pacific (Mumbai)',
  'ap-southeast-1': 'Asia Pacific (Singapore)',
  'ap-southeast-2': 'Asia Pacific (Sydney)',
  'ca-central-1': 'Canada (Central)',
  'eu-central-1': 'Europe (Frankfurt)',
  'eu-north-1': 'Europe (Stockholm)',
  'eu-west-1': 'Europe (Ireland)',
  'eu-west-2': 'Europe (London)',
  'eu-west-3': 'Europe (Paris)',
  'sa-east-1': 'South America (São Paulo)',
  'us-east-1': 'US East (N. Virginia)',
  'us-east-2': 'US East (Ohio)',
  'us-west-2': 'US West (Oregon)'
};

const regionCodes = Object.keys(awsRegions);

const RegionField = ({ fieldName }: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setFieldValue, setFieldTouched } =
    useFormikContext();

  const endpointUrl = (path([fieldName], values) as string | null) ?? '';
  const region = regionFromEndpoint(endpointUrl);
  const error = path([fieldName], touched)
    ? (path([fieldName], errors) as string | undefined)
    : undefined;

  return (
    <Autocomplete
      autoHighlight
      freeSolo
      inputValue={region}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onInputChange={(_event, newInput) => {
        const trimmed = newInput.trim().toLowerCase();
        setFieldValue(fieldName, trimmed ? bedrockEndpoint(trimmed) : '');

        // keep the prefilled model ids on the region's geography
        if (trimmed) {
          for (const field of ['defaultModel', 'fastModel']) {
            const model = path([field], values) as string | null;
            if (model) {
              setFieldValue(field, realignModelToRegion(model, trimmed));
            }
          }
        }
      }}
      options={regionCodes}
      renderInput={(params) => (
        <TextField
          {...params}
          error={Boolean(error)}
          helperText={error}
          inputProps={{
            ...params.inputProps,
            'data-testid': t(labelAwsRegion)
          }}
          label={t(labelAwsRegion)}
          placeholder={t(labelAwsRegionPlaceholder)}
          size="small"
        />
      )}
      renderOption={(props, code) => {
        const { key, ...rest } = props as { key: string };

        return (
          <li key={key} {...rest}>
            <Typography component="span" sx={{ minWidth: 116 }}>
              {code}
            </Typography>
            <Typography color="textSecondary" component="span">
              {awsRegions[code]}
            </Typography>
          </li>
        );
      }}
      sx={{ maxWidth: 360 }}
      value={region || null}
    />
  );
};

export default RegionField;
