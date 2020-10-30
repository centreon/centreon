/*
** Variables.
*/
def serie = '21.04'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-gorgone') {
      checkout scm
    }
    sh "./centreon-build/jobs/gorgone/${serie}/gorgone-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
      withSonarQubeEnv('SonarQube') {
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-analysis.sh"
      }
    }
  }
}

try {
  stage('Package') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-package.sh centos7"
        archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-package.sh centos8"
        archiveArtifacts artifacts: 'rpms-centos8.tar.gz'
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  currentBuild.result = 'FAILURE'
}
