import { type PrimitiveAtom, useAtom } from 'jotai';
import { equals, pick, type } from 'ramda';
import { useMemo } from 'react';

import { Modal } from '..';

export interface ConfirmationModalProps<TAtom> {
  atom: PrimitiveAtom<string | null>;
  disabled?: boolean;
  hasCloseButton?: boolean;
  isDanger?: boolean;
  labels: {
    cancel: string | ((atom: Awaited<TAtom> | null) => string);
    confirm: string | ((atom: Awaited<TAtom> | null) => string);
    description: string | ((atom: Awaited<TAtom> | null) => string);
    title: string | ((atom: Awaited<TAtom> | null) => string);
  };
  onCancel?: (atomData: Awaited<TAtom> | null) => void;
  onClose?: (atomData: Awaited<TAtom> | null) => void;
  onConfirm?: (atomData: Awaited<TAtom> | null) => void;
  size?: 'small' | 'medium' | 'large' | 'xlarge' | 'fullscreen';
}

interface GetLabelProps<TAtom> {
  atomData: Awaited<TAtom> | null;
  label: string | ((atom: Awaited<TAtom> | null) => string);
}

const getLabel = <TAtom,>({ label, atomData }: GetLabelProps<TAtom>): string =>
  equals(type(label), 'String')
    ? (label as string)
    : (label as (atom: Awaited<TAtom> | null) => string)(atomData);

export const ConfirmationModal = <TAtom,>({
  atom,
  labels,
  onConfirm,
  onCancel,
  onClose,
  hasCloseButton = true,
  isDanger,
  disabled,
  size
}: ConfirmationModalProps<TAtom>): JSX.Element => {
  const [atomData, setAtomData] = useAtom(atom);

  const typedAtomData = atomData as Awaited<TAtom> | null;

  const closeModal = (): void => {
    onClose?.(typedAtomData);
    setAtomData(null);
  };

  const formattedLabels = useMemo(() => {
    return {
      cancel: getLabel({ atomData: typedAtomData, label: labels.cancel }),
      confirm: getLabel({ atomData: typedAtomData, label: labels.confirm }),
      description: getLabel({
        atomData: typedAtomData,
        label: labels.description
      }),
      title: getLabel({ atomData: typedAtomData, label: labels.title })
    };
  }, [labels, typedAtomData]);

  const confirm = (): void => {
    onConfirm?.(typedAtomData);
    setAtomData(null);
  };

  const cancel = (): void => {
    onCancel?.(typedAtomData);
    setAtomData(null);
  };

  return (
    <Modal
      hasCloseButton={hasCloseButton}
      onClose={closeModal}
      open={Boolean(atomData)}
      size={size}
    >
      <Modal.Header>{formattedLabels.title}</Modal.Header>
      <Modal.Body>{formattedLabels.description}</Modal.Body>
      <Modal.Actions
        disabled={disabled}
        isDanger={isDanger}
        labels={pick(['confirm', 'cancel'], formattedLabels)}
        onCancel={cancel}
        onConfirm={confirm}
      />
    </Modal>
  );
};
