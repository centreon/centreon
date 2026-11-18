import {
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography
} from '@mui/material';

import { LoadingSkeleton } from '@centreon/ui';
import { Button, PageHeader, PageLayout } from '@centreon/ui/components';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import CreateModal from './CreateModal';
import { useCollectorActions, useCollectors } from './hooks/useCollectors';
import {
  labelActions,
  labelAddCollector,
  labelAddress,
  labelApprovalRequired,
  labelApprove,
  labelCollectors,
  labelEnrolments,
  labelExpiry,
  labelFleet,
  labelKind,
  labelLastChange,
  labelLastUsed,
  labelName,
  labelNamed,
  labelNoEnrolment,
  labelNoToken,
  labelPoller,
  labelReject,
  labelRevoke,
  labelRotateCredential,
  labelState,
  labelTokens,
  labelUses,
  labelVersion
} from './translatedLabels';
import {
  canApprove,
  canReject,
  canRotate,
  formatDate,
  formatUses,
  getStateLabel,
  getTokenStatus
} from './utils';

const Header = ({ labels }: { labels: Array<string> }): JSX.Element => {
  const { t } = useTranslation();

  return (
    <TableHead>
      <TableRow>
        {labels.map((label) => (
          <TableCell key={label}>{t(label)}</TableCell>
        ))}
      </TableRow>
    </TableHead>
  );
};

/**
 * Configuration > Pollers > Collectors: the enrolment tokens and the collectors that used them.
 * Admin only (the API answers 403 to anyone else).
 */
const CollectorsPage = (): JSX.Element => {
  const { t } = useTranslation();
  const [isCreating, setIsCreating] = useState(false);
  const { enrolments, tokens, isLoading } = useCollectors();
  const { create, run, isMutating } = useCollectorActions();

  const kindLabel = (kind: string): string =>
    t(kind === 'fleet' ? labelFleet : labelNamed);

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelCollectors)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <div className="flex flex-col gap-6">
          <div>
            <Button
              aria-label={t(labelAddCollector)}
              data-testid="add-collector"
              onClick={() => setIsCreating(true)}
              size="small"
            >
              {t(labelAddCollector)}
            </Button>
          </div>
          {isLoading ? (
            <LoadingSkeleton />
          ) : (
            <>
              <Typography variant="h6">{t(labelEnrolments)}</Typography>
              <TableContainer component={Paper}>
                <Table aria-label={t(labelEnrolments)} size="small">
                  <Header
                    labels={[
                      labelName,
                      labelKind,
                      labelPoller,
                      labelState,
                      labelAddress,
                      labelVersion,
                      labelLastChange,
                      labelActions
                    ]}
                  />
                  <TableBody>
                    {enrolments.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={8}>{t(labelNoEnrolment)}</TableCell>
                      </TableRow>
                    )}
                    {enrolments.map((enrolment) => (
                      <TableRow data-testid={enrolment.id} key={enrolment.id}>
                        <TableCell>{enrolment.name}</TableCell>
                        <TableCell>{kindLabel(enrolment.kind)}</TableCell>
                        <TableCell>
                          {enrolment.poller_name ?? enrolment.poller_id ?? '-'}
                        </TableCell>
                        <TableCell>{t(getStateLabel(enrolment))}</TableCell>
                        <TableCell>{enrolment.address}</TableCell>
                        <TableCell>{enrolment.agent_version}</TableCell>
                        <TableCell>
                          {formatDate(enrolment.updated_at)}
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-2">
                            {canApprove(enrolment) && (
                              <Button
                                disabled={isMutating}
                                onClick={() =>
                                  run({ id: enrolment.id, kind: 'approve' })
                                }
                                size="small"
                              >
                                {t(labelApprove)}
                              </Button>
                            )}
                            {canReject(enrolment) && (
                              <Button
                                disabled={isMutating}
                                isDanger
                                onClick={() =>
                                  run({ id: enrolment.id, kind: 'reject' })
                                }
                                size="small"
                                variant="secondary"
                              >
                                {t(labelReject)}
                              </Button>
                            )}
                            {canRotate(enrolment) && (
                              <Button
                                disabled={isMutating}
                                onClick={() =>
                                  run({
                                    kind: 'rotate',
                                    pollerId: enrolment.poller_id as number
                                  })
                                }
                                size="small"
                                variant="secondary"
                              >
                                {t(labelRotateCredential)}
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
              <Typography variant="h6">{t(labelTokens)}</Typography>
              <TableContainer component={Paper}>
                <Table aria-label={t(labelTokens)} size="small">
                  <Header
                    labels={[
                      labelName,
                      labelKind,
                      labelUses,
                      labelExpiry,
                      labelState,
                      labelApprovalRequired,
                      labelLastUsed,
                      labelActions
                    ]}
                  />
                  <TableBody>
                    {tokens.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={8}>{t(labelNoToken)}</TableCell>
                      </TableRow>
                    )}
                    {tokens.map((token) => (
                      <TableRow data-testid={token.id} key={token.id}>
                        <TableCell>
                          {token.poller_name ?? token.name ?? '-'}
                        </TableCell>
                        <TableCell>{kindLabel(token.kind)}</TableCell>
                        <TableCell>{formatUses(token)}</TableCell>
                        <TableCell>{formatDate(token.expires_at)}</TableCell>
                        <TableCell>{t(getTokenStatus(token))}</TableCell>
                        <TableCell>
                          {token.approval_required ? '✓' : ''}
                        </TableCell>
                        <TableCell>{formatDate(token.last_used_at)}</TableCell>
                        <TableCell>
                          <Button
                            disabled={isMutating}
                            isDanger
                            onClick={() =>
                              run({ id: token.id, kind: 'revoke' })
                            }
                            size="small"
                            variant="secondary"
                          >
                            {t(labelRevoke)}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </>
          )}
        </div>
      </PageLayout.Body>
      <CreateModal
        create={create}
        isMutating={isMutating}
        onClose={() => setIsCreating(false)}
        open={isCreating}
      />
    </PageLayout>
  );
};

export default CollectorsPage;
