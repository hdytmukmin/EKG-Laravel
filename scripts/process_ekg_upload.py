import argparse
import os
import sys


def main():
    parser = argparse.ArgumentParser(description="Run legacy EKG CSV processor from Laravel.")
    parser.add_argument("--dataset", required=True)
    parser.add_argument("--subject-id", required=True)
    parser.add_argument("--subject-name", required=True)
    parser.add_argument("--processor-path", required=True)
    args = parser.parse_args()

    os.environ.setdefault("MPLBACKEND", "Agg")
    os.chdir(args.processor_path)

    if args.processor_path not in sys.path:
        sys.path.insert(0, args.processor_path)

    from processdata import ProcessData

    processor = ProcessData(
        dataSet=args.dataset,
        subject_name=args.subject_name,
        subject_id=args.subject_id,
    )
    processor.process()


if __name__ == "__main__":
    main()
