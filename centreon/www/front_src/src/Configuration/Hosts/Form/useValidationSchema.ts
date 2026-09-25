import { type ObjectSchema, object } from 'yup';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<object>;
}

// Placeholder: ConfigurationBase requires a `form`.
const useValidationSchema = (): UseValidationSchemaState => ({
  validationSchema: object({})
});

export default useValidationSchema;
