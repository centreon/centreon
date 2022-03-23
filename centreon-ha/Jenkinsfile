/*
** Variables.
*/
def serie = '22.04'
def maintenanceBranch = "${serie}.x"
def qaBranch = "dev-${serie}.x"

if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else if ((env.BRANCH_NAME == 'develop') || (env.BRANCH_NAME == qaBranch)) {
  env.BUILD = 'QA'
} else {
  env.BUILD = 'CI'
}

/*
** Pipeline code.
*/
stage('Deliver sources') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-ha') {
      checkout scm
    }
    sh "./centreon-build/jobs/ha/${serie}/ha-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon HA Build Artifacts',
      reportTitles: ''
    ])
    withSonarQubeEnv('SonarQubeDev') {
      sh "./centreon-build/jobs/ha/${serie}/ha-analysis.sh"
    }
    timeout(time: 10, unit: 'MINUTES') {
      def qualityGate = waitForQualityGate()
      if (qualityGate.status != 'OK') {
        currentBuild.result = 'FAIL'
      }
    }
  }
}

stage('RPM packaging') {
  parallel 'centos7': {
    node {
      sh 'setup_centreon_build.sh'
      sh "./centreon-build/jobs/ha/${serie}/ha-package.sh centos7"
      stash name: 'rpms-centos7', includes: "output/noarch/*.rpm"
      archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
      sh 'rm -rf output'
    }
  },
  'alma8': {
    node {
      sh 'setup_centreon_build.sh'
      sh "./centreon-build/jobs/ha/${serie}/ha-package.sh alma8"
      stash name: 'rpms-alma8', includes: "output/noarch/*.rpm"
      archiveArtifacts artifacts: 'rpms-alma8.tar.gz'
      sh 'rm -rf output'
    }
  }
  if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
    error('Package stage failure.')
  }
}

if ((env.BUILD == 'RELEASE') || (env.BUILD == 'CI') || (env.BUILD == 'QA') ) {
  stage('Delivery') {
    node {
      sh 'setup_centreon_build.sh'
      unstash 'rpms-centos7'
      unstash 'rpms-alma8'
      sh "./centreon-build/jobs/ha/${serie}/ha-delivery.sh"
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Delivery stage failure.');
    }
  }
}
