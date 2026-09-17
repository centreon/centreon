import { type ObjectSchema, object } from 'yup';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

/**
 * Placeholder until the form is implemented.
 * `ConfigurationBase` requires a `form` prop, so the scaffold supplies an empty one.
 */
const useValidationSchema = (): UseValidationSchemaState => ({
  validationSchema: object({})
});

export default useValidationSchema;
