import argparse
import asyncio
import logging

import asyncpg

from app.billing import recover_pending
from app.config import settings


async def run(limit: int, max_retries: int) -> None:
    pool = await asyncpg.create_pool(settings.database_url, min_size=1, max_size=2)
    try:
        settled, failed = await recover_pending(pool, limit, max_retries)
        logging.getLogger("azkia.billing.worker").info("settled=%s failed=%s", settled, failed)
    finally:
        await pool.close()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=100)
    parser.add_argument("--max-retries", type=int, default=12)
    args = parser.parse_args()
    logging.basicConfig(level=logging.INFO)
    asyncio.run(run(args.limit, args.max_retries))


if __name__ == "__main__":
    main()
