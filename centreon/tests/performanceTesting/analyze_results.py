import pandas as pd
import seaborn as sns
import matplotlib.pyplot as plt

def analyze_jtl(file_path):
    df = pd.read_csv(file_path)

    summary = "## Analyse des résultats JTL\n\n"

    # 1. Exporter toutes les erreurs dans errors_summary.csv
    errors = df[df['responseCode'] != '200'][['label', 'responseCode', 'failureMessage']]
    if not errors.empty:
        errors.to_csv("errors_summary.csv", index=False)
        summary += f"⚠️ {len(errors)} erreurs détectées (exportées dans errors_summary.csv)\n\n"
    else:
        summary += "✅ Aucune erreur détectée\n\n"

    # 2. Percentiles sur temps de réponse (elapsed)
    p50 = df['elapsed'].quantile(0.5)
    p90 = df['elapsed'].quantile(0.9)
    p99 = df['elapsed'].quantile(0.99)
    summary += f"⏱ Temps de réponse (ms): P50={p50:.2f}, P90={p90:.2f}, P99={p99:.2f}\n\n"

    # 3. Analyse par type de requête (label)
    grouped = df.groupby("label")["elapsed"].agg(["mean", "count", "max", "min"])
    summary += "### Analyse par type de requête (label)\n\n"
    summary += grouped.to_markdown() + "\n\n"

    # 4. Création graphique boxplot des temps de réponse par label
    plt.figure(figsize=(12,6))
    sns.boxplot(data=df, x="label", y="elapsed")
    plt.xticks(rotation=90)
    plt.title("Distribution des temps de réponse par requête")
    plt.tight_layout()
    plt.savefig("response_times.png")
    plt.close()

    summary += "📊 Un graphique des temps de réponse a été généré : response_times.png\n"

    # Retourne résumé + si test passé ou pas (pas d'erreur)
    passed = errors.empty
    return summary, passed

if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python analyze_results.py <file.csv>")
        sys.exit(1)

    file = sys.argv[1]
    report, passed = analyze_jtl(file)
    print(report)

    if not passed:
        print("\n❗ Test Failed: Thresholds violated.\n")
