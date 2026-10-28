import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { string } from 'yup';

import type { NamedEntity } from '../../models';
import {
  labelGeneralInformation,
  labelHost,
  labelMustBeAtMostCharacters,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelRequired,
  labelServiceBasicInformation
} from '../../translatedLabels';
import type { FormSection } from './models';
import {
  buildSelector,
  namedEntitySchema,
  optionalNamedEntityDecoder
} from './selector';

// F01: uniqueness on the host is left to the server, which alone sees the
// other services of that host.
const nameMaxLength = 200;
// The set the legacy `illegal_object_name_chars` option strips, which the API
// refuses instead.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;

// Written as is on a line of the engine configuration: no control character.
const hasControlCharacter = (value: string): boolean =>
  [...value].some((character) => {
    const code = character.charCodeAt(0);

    return code < 32 || code === 127;
  });

// What the server stores: trimmed, inner runs of spaces collapsed.
const normalizeName = (name: string | undefined): string =>
  (name ?? '').trim().replace(/\s{2,}/g, ' ');

// Placeholder until the backend exposes the form's own selectors, modeled on
// the `/configuration/hosts/*` ones: granted by service write access, not by
// each object's ACL.
export const serviceFormHostsEndpoint = '/configuration/services/hosts';

interface GeneralInformationDetail {
  host: NamedEntity | null;
  // Read for the panel header's switch, which enables and disables the
  // service on its own request: the form never sends it.
  isActivated: boolean;
  name: string;
}

// The Figma panel's first section: the name and host of one service (F01,
// F02). A mass change edits a selection, so it has neither and the section is
// left out.
export const generalInformation: FormSection<GeneralInformationDetail> = {
  defaultValues: {
    // Empty even when the form opens from a host (decision D8).
    host: null,
    // A service is created enabled; the header switch changes it afterwards.
    isActivated: true,
    name: ''
  },
  detailDecoders: {
    host: optionalNamedEntityDecoder('Host'),
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string
  },
  detailKeyMap: { isActivated: 'activated' },
  getInputs: ({ t }) => [
    {
      additionalLabel: t(labelServiceBasicInformation),
      fieldName: 'service-identity',
      grid: {
        className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
        columns: [
          {
            dataTestId: 'service-form-name',
            fieldName: 'name',
            label: t(labelName),
            required: true,
            type: InputType.Text
          },
          {
            connectedAutocomplete: buildSelector({
              endpoint: serviceFormHostsEndpoint,
              queryKey: 'service-form-host'
            }),
            dataTestId: 'service-form-host',
            fieldName: 'host',
            label: t(labelHost),
            required: true,
            type: InputType.SingleConnectedAutocomplete
          }
        ]
      },
      label: 'service-form-identity',
      type: InputType.Grid
    }
  ],
  getSchema: ({ t }) => ({
    host: namedEntitySchema.required(t(labelRequired)),
    // Trimmed as the server normalises it, so blanks report as missing.
    name: string()
      .trim()
      .max(
        nameMaxLength,
        t(labelMustBeAtMostCharacters, {
          label: t(labelName),
          max: nameMaxLength
        })
      )
      .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
      .test(
        'has-no-control-character',
        t(labelNameContainsForbiddenCharacters),
        (value) => !hasControlCharacter(value ?? '')
      )
      .required(t(labelRequired))
  }),
  isAvailable: ({ isMassChange }) => !isMassChange,
  label: labelGeneralInformation,
  toPayload: (values) => {
    const { host, name } = values as {
      host?: NamedEntity | null;
      name?: string;
    };

    return {
      host_id: host?.id ?? null,
      name: normalizeName(name)
    };
  }
};
