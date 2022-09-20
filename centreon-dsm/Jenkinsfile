/*
** Variables.
*/
def serie = '22.10'
def stableBranch = "master"
def devBranch = "develop"
env.REF_BRANCH = 'master'
env.PROJECT='centreon-dsm'
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
  env.REPO = 'testing'
} else if (env.BRANCH_NAME == stableBranch) {
  env.BUILD = 'REFERENCE'
} else if (env.BRANCH_NAME == devBranch) {
  env.BUILD = 'QA'
  env.REPO = 'unstable'
} else {
  env.BUILD = 'CI'
}

env.BUILD_BRANCH = env.BRANCH_NAME
if (env.CHANGE_BRANCH) {
  env.BUILD_BRANCH = env.CHANGE_BRANCH
}

/*
** Functions
*/
def isStableBuild() {
  return ((env.BUILD == 'REFERENCE') || (env.BUILD == 'QA'))
}

def checkoutCentreonBuild() {
  dir('centreon-build') {
    retry(3) {
      checkout resolveScm(
        source: [
          $class: 'GitSCMSource',
          remote: 'https://github.com/centreon/centreon-build.git',
          credentialsId: 'technique-ci',
          traits: [[$class: 'jenkins.plugins.git.traits.BranchDiscoveryTrait']]
        ],
        targets: [env.BUILD_BRANCH, 'master']
      )
    }
  }
}

/*
** Pipeline code.
*/
stage('Deliver sources') {
  node {
    checkoutCentreonBuild()
    dir('centreon-dsm') {
      checkout scm
    }
    sh "./centreon-build/jobs/dsm/${serie}/dsm-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon DSM Build Artifacts',
      reportTitles: ''
    ])
  }
}

try {
  stage('Unit tests // RPM/DEB Packaging // Sonar analysis') {
    parallel 'unit tests centos7': {
      node {
        checkoutCentreonBuild()
        sh "./centreon-build/jobs/dsm/${serie}/dsm-unittest.sh centos7"

        if (env.CHANGE_ID) { // pull request to comment with coding style issues
          ViolationsToGitHub([
            repositoryName: 'centreon-dsm',
            pullRequestId: env.CHANGE_ID,

            createSingleFileComments: true,
            commentOnlyChangedContent: true,
            commentOnlyChangedFiles: true,
            keepOldComments: false,

            commentTemplate: "**{{violation.severity}}**: {{violation.message}}",

            violationConfigs: [
              [parser: 'CHECKSTYLE', pattern: '.*/codestyle-be.xml$', reporter: 'Checkstyle']
            ]
          ])
        }

        discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          qualityGates: [[threshold: 1, type: 'DELTA', unstable: false]],
          tool: phpCodeSniffer(id: 'phpcs', name: 'phpcs', pattern: 'codestyle-be.xml'),
          trendChartType: 'NONE'
        )

        withSonarQubeEnv('SonarQubeDev') {
          sh "./centreon-build/jobs/dsm/${serie}/dsm-analysis.sh"
        }
        timeout(time: 10, unit: 'MINUTES') {
          def qualityGate = waitForQualityGate()
          if (qualityGate.status != 'OK') {
            currentBuild.result = 'FAIL'
          }
        }
      }
    },
    'RPM Packaging centos7': {
      node {
        checkoutCentreonBuild()
        sh "./centreon-build/jobs/dsm/${serie}/dsm-package.sh centos7"
        archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
        stash name: "rpms-centos7", includes: 'output/noarch/*.rpm'
        sh 'rm -rf output'
      }
    },
    'RPM Packaging alma8': {
      node {
        checkoutCentreonBuild()
        sh "./centreon-build/jobs/dsm/${serie}/dsm-package.sh alma8"
        archiveArtifacts artifacts: 'rpms-alma8.tar.gz'
        stash name: "rpms-alma8", includes: 'output/noarch/*.rpm'
        sh 'rm -rf output'
      }
    },
    'Debian bullseye packaging and signing': {
      node {
        dir('centreon-dsm') {
          checkout scm
        }
        sh 'docker run -i --entrypoint "/src/centreon-dsm/ci/scripts/centreon-dsm-package.sh" -w "/src" -v "$PWD:/src" -e "DISTRIB=bullseye" -e "VERSION=$VERSION" -e "RELEASE=$RELEASE" registry.centreon.com/centreon-debian11-dependencies:22.10'
        stash name: 'Debian11', includes: '*.deb'
        archiveArtifacts artifacts: "*"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests // RPM/DEB Packaging // Sonar analysis stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'QA') || (env.BUILD == 'CI')) {
    stage('Delivery') {
      node {
        unstash 'rpms-centos7'
        unstash 'rpms-alma8'
        checkoutCentreonBuild()
        sh "./centreon-build/jobs/dsm/${serie}/dsm-delivery.sh"
        withCredentials([usernamePassword(credentialsId: 'nexus-credentials', passwordVariable: 'NEXUS_PASSWORD', usernameVariable: 'NEXUS_USERNAME')]) {
          checkout scm
          unstash "Debian11"
          sh '''for i in $(echo *.deb)
                do
                  curl -u $NEXUS_USERNAME:$NEXUS_PASSWORD -H "Content-Type: multipart/form-data" --data-binary "@./$i" https://apt.centreon.com/repository/22.10-$REPO/
                done
             '''
        }
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure');
      }
    }
  }
}
finally {
  buildStatus = currentBuild.result ?: 'SUCCESS';
  if ((buildStatus != 'SUCCESS') && ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE'))) {
    slackSend channel: '#monitoring-metrology', message: "@channel Centreon DSM build ${env.BUILD_NUMBER} of branch ${env.BRANCH_NAME} was broken by ${source.COMMITTER}. Please fix it ASAP."
  }
}
