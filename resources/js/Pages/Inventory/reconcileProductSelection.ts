interface ProductLine {
    product_id: number;
}

export function reconcileProductSelection<T extends ProductLine>(
    lines: T[],
    selectableIds: ReadonlySet<number>,
    selectedIds: number[],
    limit: number,
    makeLine: (productId: number) => T,
): T[] {
    const selected = new Set(selectedIds);
    const result = lines.filter((line) =>
        !selectableIds.has(line.product_id) || selected.has(line.product_id),
    );
    const alreadyAdded = new Set(result.map((line) => line.product_id));

    for (const id of selected) {
        if (result.length >= limit) break;
        if (!selectableIds.has(id) || alreadyAdded.has(id)) continue;
        result.push(makeLine(id));
        alreadyAdded.add(id);
    }

    return result;
}
